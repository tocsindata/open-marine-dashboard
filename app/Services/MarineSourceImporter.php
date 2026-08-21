<?php

namespace App\Services;

use App\Models\SourceImportStep;
use App\Models\UserSourceCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MarineSourceImporter
{
    public function import(SourceImportStep $step): array
    {
        $provider = config("marine_sources.providers.{$step->provider_key}");
        if (! is_array($provider) || ! ($provider['enabled'] ?? false)) {
            throw new RuntimeException("Source provider {$step->provider_key} is not enabled.");
        }

        $providerNeedsToken = ($provider['tier'] ?? null) === 'user_token';
        $credential = $providerNeedsToken
            ? UserSourceCredential::query()->where('user_id', $step->user_id)->where('provider_key', $step->provider_key)->where('enabled', true)->firstOrFail()
            : null;

        if ($step->provider_key === 'eccc_marine_bulletins') {
            return $this->importEcccMarineBulletins($step, $provider);
        }

        [$request, $query] = $this->request($step->provider_key, $credential, $step);
        $response = $request->get($provider['endpoint'], $query)->throw();
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Provider did not return a JSON object or array.');
        }

        $records = $this->records($step->provider_key, $payload, $step);
        $written = 0;
        DB::transaction(function () use ($records, $provider, $step, &$written): void {
            foreach ($records as $record) {
                $externalId = (string) ($record['id'] ?? $record['identifier'] ?? hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)));
                $encoded = json_encode($record, JSON_THROW_ON_ERROR);
                DB::table('source_records')->updateOrInsert(
                    ['provider_key' => $step->provider_key, 'user_id' => $step->user_id, 'external_id' => Str::limit($externalId, 190, '')],
                    ['id' => (string) str()->ulid(), 'record_type' => $provider['record_type'], 'payload' => $encoded, 'payload_hash' => hash('sha256', $encoded), 'retrieved_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                );
                $written++;
            }
        });

        return ['http_status' => $response->status(), 'records_seen' => count($records), 'records_written' => $written];
    }

    private function importEcccMarineBulletins(SourceImportStep $step, array $provider): array
    {
        $currentCycle = intdiv((int) now('UTC')->format('G'), 6) * 6;
        $hours = [];
        for ($offset = 0; $offset < 24; $offset += 6) {
            $hours[] = sprintf('%02d', ($currentCycle - $offset + 24) % 24);
        }

        $files = [];
        $directory = null;
        $httpStatus = null;
        foreach ($hours as $hour) {
            $candidate = rtrim($provider['endpoint'], '/').'/'.$hour.'/';
            $listing = Http::accept('text/html')->timeout(15)->retry(1, 500)->get($candidate)->throw();
            $httpStatus = $listing->status();
            preg_match_all('/href=["\']([^"\']+_en\.xml)["\']/i', $listing->body(), $matches);
            $files = array_values(array_unique(array_map('basename', $matches[1] ?? [])));
            if ($files !== []) {
                $directory = $candidate;
                break;
            }
        }

        if (! $directory || $files === []) {
            throw new RuntimeException('No current English Pacific marine bulletin files were found in the ECCC Datamart.');
        }

        sort($files);
        $files = array_slice($files, -40);
        $written = 0;
        foreach ($files as $file) {
            if (! preg_match('/^[A-Za-z0-9_.-]+_en\.xml$/', $file)) {
                continue;
            }

            $url = $directory.rawurlencode($file);
            $response = Http::accept('application/xml')->timeout(15)->retry(1, 500)->get($url)->throw();
            $xmlText = $response->body();
            $plainText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xmlText))));
            $payload = [
                'id' => $file,
                'filename' => $file,
                'source_url' => $url,
                'issued_at' => substr($file, 0, 20),
                'text' => $plainText,
            ];
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            DB::table('source_records')->updateOrInsert(
                ['provider_key' => $step->provider_key, 'user_id' => null, 'external_id' => $file],
                [
                    'id' => (string) str()->ulid(),
                    'record_type' => $provider['record_type'],
                    'payload' => $encoded,
                    'payload_hash' => hash('sha256', $encoded),
                    'retrieved_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $written++;
        }

        return ['http_status' => $httpStatus, 'records_seen' => count($files), 'records_written' => $written];
    }

    public function testCredential(string $providerKey, string $token, float $latitude, float $longitude): string
    {
        $credential = new UserSourceCredential(['provider_key' => $providerKey, 'encrypted_token' => $token, 'default_latitude' => $latitude, 'default_longitude' => $longitude]);
        [$request, $query] = $this->request($providerKey, $credential);
        $provider = config("marine_sources.providers.{$providerKey}");
        $response = $request->get($provider['endpoint'], $query)->throw();

        return "Connected successfully (HTTP {$response->status()}).";
    }

    private function request(string $providerKey, ?UserSourceCredential $credential, ?SourceImportStep $step = null): array
    {
        $request = Http::acceptJson()->timeout(15)->retry(2, 500);

        $location = null;
        if ($step && str_starts_with($step->scope_key, 'vessel:') && $step->user_id) {
            $vesselId = substr($step->scope_key, 7);
            $position = DB::table('vessel_positions')
                ->join('vessels', 'vessels.id', '=', 'vessel_positions.vessel_id')
                ->where('vessels.id', $vesselId)
                ->where('vessels.owner_id', $step->user_id)
                ->orderByDesc('vessel_positions.recorded_at')
                ->first(['vessels.name', 'vessel_positions.latitude', 'vessel_positions.longitude']);
            $location = $position ? ['name' => $position->name, 'latitude' => $position->latitude, 'longitude' => $position->longitude] : null;
        } elseif ($step) {
            $location = config("marine_sources.forecast_locations.{$step->scope_key}");
        }

        return match ($providerKey) {
            'open_meteo_weather' => [$request, [
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,precipitation,weather_code,cloud_cover,pressure_msl,visibility,cape,wind_speed_10m,wind_direction_10m,wind_gusts_10m',
                'hourly' => 'temperature_2m,relative_humidity_2m,precipitation_probability,weather_code,cloud_cover,pressure_msl,wind_speed_10m,wind_direction_10m,wind_gusts_10m,visibility,cape',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,wind_speed_10m_max,wind_gusts_10m_max,sunrise,sunset',
                'forecast_hours' => 48,
                'wind_speed_unit' => 'kn',
                'timezone' => 'auto',
            ]],
            'open_meteo_marine' => [$request, [
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'current' => 'wave_height,wave_direction,wave_period,swell_wave_height,swell_wave_direction,swell_wave_period,sea_surface_temperature,ocean_current_velocity,ocean_current_direction,sea_level_height_msl',
                'hourly' => 'wave_height,wave_direction,wave_period,swell_wave_height,swell_wave_direction,swell_wave_period,sea_surface_temperature,ocean_current_velocity,ocean_current_direction,sea_level_height_msl',
                'forecast_hours' => 48,
                'wind_speed_unit' => 'kn',
                'timezone' => 'auto',
            ]],
            'eccc_alerts' => [$request, ['limit' => 100, 'f' => 'json', 'sortby' => '-datetime']],
            'chs_stations', 'usgs_earthquakes' => [$request, []],
            'vesselfinder_livedata' => [$request, ['userkey' => $credential?->encrypted_token, 'format' => 'json', 'interval' => max(1, (int) $credential?->interval_minutes)]],
            'stormglass' => [$request->withHeaders(['Authorization' => (string) $credential?->encrypted_token]), ['lat' => $credential?->default_latitude, 'lng' => $credential?->default_longitude, 'params' => 'waveHeight,waveDirection,wavePeriod,windSpeed,windDirection,airTemperature,waterTemperature']],
            'worldtides' => [$request, ['key' => $credential?->encrypted_token, 'lat' => $credential?->default_latitude, 'lon' => $credential?->default_longitude, 'extremes' => '', 'heights' => '', 'days' => 3]],
            default => throw new RuntimeException("No importer exists for {$providerKey}."),
        };
    }

    private function records(string $providerKey, array $payload, ?SourceImportStep $step = null): array
    {
        if (in_array($providerKey, ['open_meteo_weather', 'open_meteo_marine'], true)) {
            $scopeKey = $step?->scope_key;
            if ($scopeKey && str_starts_with($scopeKey, 'vessel:') && $step?->user_id) {
                $vesselId = substr($scopeKey, 7);
                $position = DB::table('vessel_positions')
                    ->join('vessels', 'vessels.id', '=', 'vessel_positions.vessel_id')
                    ->where('vessels.id', $vesselId)
                    ->where('vessels.owner_id', $step->user_id)
                    ->orderByDesc('vessel_positions.recorded_at')
                    ->first(['vessels.name', 'vessel_positions.latitude', 'vessel_positions.longitude']);
                $location = $position ? ['name' => $position->name.' current position', 'latitude' => $position->latitude, 'longitude' => $position->longitude] : [];
            } else {
                $location = $scopeKey ? config("marine_sources.forecast_locations.{$scopeKey}", []) : [];
            }

            return [[
                'id' => (string) ($scopeKey ?? hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))),
                'location_key' => $scopeKey,
                'location_name' => $location['name'] ?? $scopeKey,
                'requested_latitude' => $location['latitude'] ?? null,
                'requested_longitude' => $location['longitude'] ?? null,
                'forecast' => $payload,
            ]];
        }

        return match ($providerKey) {
            'eccc_alerts', 'usgs_earthquakes' => array_values($payload['features'] ?? []),
            'chs_stations' => array_is_list($payload) ? $payload : array_values($payload['stations'] ?? []),
            'vesselfinder_livedata' => array_is_list($payload) ? $payload : [],
            'stormglass' => array_values($payload['hours'] ?? []),
            'worldtides' => array_values(array_merge($payload['heights'] ?? [], $payload['extremes'] ?? [])),
            default => [],
        };
    }
}
