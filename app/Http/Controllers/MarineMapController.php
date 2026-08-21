<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\SourceImportStep;
use App\Models\Vessel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarineMapController extends Controller
{
    public function navigation(Request $request): View
    {
        return view('navigation', [
            'vessels' => $request->user()->vessels()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $sharedProviders = ['open_meteo_weather', 'open_meteo_marine', 'eccc_marine_bulletins', 'eccc_alerts', 'chs_stations', 'usgs_earthquakes'];

        $positions = DB::table('vessels')
            ->where('vessels.owner_id', $user->id)
            ->where('vessels.status', 'active')
            ->leftJoin('vessel_positions', function ($join): void {
                $join->on('vessel_positions.vessel_id', '=', 'vessels.id')
                    ->whereRaw('vessel_positions.recorded_at = (select max(vp.recorded_at) from vessel_positions vp where vp.vessel_id = vessels.id)');
            })
            ->get(['vessels.id', 'vessels.name', 'vessels.mmsi', 'vessel_positions.latitude', 'vessel_positions.longitude', 'vessel_positions.speed_knots', 'vessel_positions.course_degrees', 'vessel_positions.recorded_at']);

        $sharedRows = DB::table('source_records')
            ->whereNull('user_id')
            ->whereIn('provider_key', $sharedProviders)
            ->where('retrieved_at', '>=', now()->subDays(2))
            ->orderByDesc('retrieved_at')
            ->limit(2000)
            ->get(['provider_key', 'payload', 'retrieved_at']);

        $userRows = DB::table('source_records')
            ->where('user_id', $user->id)
            ->whereIn('provider_key', ['open_meteo_weather', 'open_meteo_marine', 'vesselfinder_livedata', 'stormglass', 'worldtides'])
            ->where('retrieved_at', '>=', now()->subDays(4))
            ->orderByDesc('retrieved_at')
            ->limit(2500)
            ->get(['provider_key', 'payload', 'retrieved_at']);

        $sources = collect($sharedProviders)->mapWithKeys(fn (string $key): array => [
            $key => $this->sourceStatus(
                $sharedRows->where('provider_key', $key)->concat($userRows->where('provider_key', $key)),
                $key,
            ),
        ]);
        foreach (['vesselfinder_livedata', 'stormglass', 'worldtides'] as $key) {
            $sources->put($key, $this->sourceStatus($userRows->where('provider_key', $key), $key));
        }

        $aisRows = $userRows->where('provider_key', 'vesselfinder_livedata');
        $aisStatus = $sources->get('vesselfinder_livedata');
        $ais = $this->payloads($aisRows);

        $response = response()->json([
            'generated_at' => now()->toIso8601String(),
            'sources' => $sources,
            'vessels' => $positions,
            'weather_alerts' => $this->payloads($sharedRows->where('provider_key', 'eccc_alerts')),
            'official_marine_bulletins' => $this->payloads($sharedRows->where('provider_key', 'eccc_marine_bulletins')),
            'water_level_stations' => $this->payloads($sharedRows->where('provider_key', 'chs_stations')),
            'earthquakes' => $this->payloads($sharedRows->where('provider_key', 'usgs_earthquakes')),
            'weather_forecasts' => $this->payloads(
                $sharedRows->where('provider_key', 'open_meteo_weather')->concat($userRows->where('provider_key', 'open_meteo_weather')),
            ),
            'marine_forecasts' => $this->payloads(
                $sharedRows->where('provider_key', 'open_meteo_marine')->concat($userRows->where('provider_key', 'open_meteo_marine')),
            ),
            'paid_marine_forecasts' => $this->payloads($userRows->where('provider_key', 'stormglass')),
            'paid_tide_forecasts' => $this->payloads($userRows->where('provider_key', 'worldtides')),
            'ais' => [
                'available' => $ais->isNotEmpty(),
                'status' => $aisStatus['status'],
                'message' => match ($aisStatus['status']) {
                    'current' => 'Licensed VesselFinder AIS records are displayed. Confirm timestamps and maintain a proper lookout.',
                    'stale' => 'The latest licensed AIS records are stale. Do not use them for collision avoidance.',
                    default => 'No current licensed AIS records are available. Marine never simulates surrounding traffic.',
                },
                'vessels' => $ais,
            ],
        ]);

        return $response->header('Cache-Control', 'private, no-store, max-age=0')->header('Pragma', 'no-cache');
    }

    private function payloads(Collection $rows): Collection
    {
        return $rows->map(function (object $row): ?array {
            $payload = json_decode($row->payload, true);
            if (! is_array($payload)) {
                return null;
            }
            $payload['_marine_retrieved_at'] = Carbon::parse($row->retrieved_at)->toIso8601String();

            return $payload;
        })->filter()->values();
    }

    private function sourceStatus(Collection $records, string $providerKey): array
    {
        $provider = config("marine_sources.providers.{$providerKey}", []);
        $staleAfterMinutes = max(1, (int) ($provider['stale_after_minutes'] ?? 180));
        $latestValue = $records->max('retrieved_at');
        $latestAt = $latestValue ? Carbon::parse($latestValue) : null;

        return [
            'label' => (string) ($provider['name'] ?? $providerKey),
            'status' => $latestAt ? ($latestAt->lt(now()->subMinutes($staleAfterMinutes)) ? 'stale' : 'current') : 'unavailable',
            'retrieved_at' => $latestAt?->toIso8601String(),
            'record_count' => $records->count(),
            'stale_after_minutes' => $staleAfterMinutes,
            'documentation' => (string) ($provider['documentation'] ?? ''),
        ];
    }

    public function storePosition(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vessel_id' => ['required', 'string', Rule::exists('vessels', 'id')->where(fn ($query) => $query->where('owner_id', $request->user()->id)->where('status', 'active'))],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed_knots' => ['nullable', 'numeric', 'between:0,150'],
            'course_degrees' => ['nullable', 'numeric', 'between:0,360'],
            'accuracy_m' => ['nullable', 'numeric', 'between:0,100000'],
            'enable_location_forecasts' => ['nullable', 'boolean'],
        ]);

        $vessel = Vessel::query()->where('owner_id', $request->user()->id)->findOrFail($validated['vessel_id']);
        $position = $vessel->positions()->create([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'speed_knots' => $validated['speed_knots'] ?? null,
            'course_degrees' => $validated['course_degrees'] ?? null,
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'source' => 'manual',
            'recorded_at' => now(),
        ]);
        $locationForecastsEnabled = (bool) ($validated['enable_location_forecasts'] ?? false);

        foreach (['open_meteo_weather' => 30, 'open_meteo_marine' => 60] as $providerKey => $interval) {
            $identity = [
                'provider_key' => $providerKey,
                'scope_key' => "vessel:{$vessel->id}",
                'user_id' => $request->user()->id,
            ];

            if ($locationForecastsEnabled) {
                SourceImportStep::query()->updateOrCreate($identity, [
                    'enabled' => true,
                    'interval_minutes' => $interval,
                    'next_due_at' => now(),
                    'locked_at' => null,
                    'consent_granted_at' => now(),
                    'consent_revoked_at' => null,
                ]);
            } else {
                SourceImportStep::query()
                    ->where($identity)
                    ->update([
                        'enabled' => false,
                        'locked_at' => null,
                        'consent_revoked_at' => now(),
                    ]);
            }
        }

        AuditEvent::create([
            'actor_id' => $request->user()->id, 'channel' => 'web', 'action' => 'vessel.position_recorded',
            'target_type' => Vessel::class, 'target_id' => $vessel->id,
            'metadata' => [
                'position_id' => $position->id,
                'source' => 'manual',
                'location_forecasts_enabled' => $locationForecastsEnabled,
            ],
            'occurred_at' => now(),
        ]);

        return back()->with('status', 'Vessel position updated.');
    }
}
