<?php

namespace App\Http\Controllers;

use App\Models\SourceImportStep;
use App\Models\UserSourceCredential;
use App\Services\MarineSourceImporter;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SourceCredentialController extends Controller
{
    public function index(Request $request): View
    {
        $providers = collect(config('marine_sources.providers'))
            ->filter(fn (array $provider): bool => $provider['tier'] === 'user_token');

        return view('profile.sources', [
            'providers' => $providers,
            'credentials' => UserSourceCredential::query()
                ->ownedBy($request->user())
                ->get()
                ->keyBy('provider_key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $providerKeys = collect(config('marine_sources.providers'))
            ->filter(fn (array $provider): bool => $provider['tier'] === 'user_token')
            ->keys()->all();

        $data = $request->validate([
            'provider_key' => ['required', Rule::in($providerKeys)],
            'token' => ['nullable', 'string', 'min:8', 'max:2000'],
            'label' => ['nullable', 'string', 'max:100'],
            'default_latitude' => ['required', 'numeric', 'between:-90,90'],
            'default_longitude' => ['required', 'numeric', 'between:-180,180'],
            'interval_minutes' => ['required', 'integer', 'between:15,1440'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $credential = UserSourceCredential::query()
            ->ownedBy($request->user())
            ->where('provider_key', $data['provider_key'])
            ->first();

        if ($credential) {
            Gate::authorize('update', $credential);
        }

        if (! $credential && empty($data['token'])) {
            return back()->withErrors(['token' => 'An API token is required when adding a provider.'])->withInput();
        }

        $values = [
            'label' => $data['label'] ?? null,
            'default_latitude' => $data['default_latitude'],
            'default_longitude' => $data['default_longitude'],
            'interval_minutes' => $data['interval_minutes'],
            'enabled' => $request->boolean('enabled'),
            'last_test_result' => null,
            'last_test_message' => null,
        ];

        if (! empty($data['token'])) {
            $values['encrypted_token'] = $data['token'];
            $values['token_hint'] = mb_substr($data['token'], -4);
            $values['last_tested_at'] = null;
        }

        $credential = $request->user()->sourceCredentials()->updateOrCreate(
            ['provider_key' => $data['provider_key']],
            $values,
        );

        SourceImportStep::query()->updateOrCreate(
            ['provider_key' => $credential->provider_key, 'scope_key' => "user:{$request->user()->id}"],
            [
                'user_id' => $request->user()->id,
                'enabled' => $credential->enabled,
                'interval_minutes' => $credential->interval_minutes,
                'next_due_at' => now(),
                'locked_at' => null,
            ],
        );

        return back()->with('status', 'Source settings saved. The token is encrypted and will not be displayed again.');
    }

    public function test(Request $request, string $providerKey, MarineSourceImporter $importer): RedirectResponse
    {
        $credential = UserSourceCredential::query()
            ->ownedBy($request->user())
            ->where('provider_key', $providerKey)
            ->firstOrFail();
        Gate::authorize('update', $credential);

        try {
            $message = $importer->testCredential(
                $providerKey,
                $credential->encrypted_token,
                (float) $credential->default_latitude,
                (float) $credential->default_longitude,
            );
            $credential->update(['last_tested_at' => now(), 'last_test_result' => 'success', 'last_test_message' => $message]);

            return back()->with('status', $message);
        } catch (Throwable $exception) {
            Log::warning('Marine provider credential test failed.', [
                'provider_key' => $providerKey,
                'user_id' => (string) $request->user()->id,
                'exception_type' => $exception::class,
                'http_status' => $exception instanceof RequestException
                    ? $exception->response->status()
                    : null,
            ]);

            $message = 'The provider connection test failed. Verify the token and settings, then try again.';
            $credential->update(['last_tested_at' => now(), 'last_test_result' => 'failed', 'last_test_message' => $message]);

            return back()->withErrors(['provider' => $message]);
        }
    }

    public function destroy(Request $request, string $providerKey): RedirectResponse
    {
        $credential = UserSourceCredential::query()
            ->ownedBy($request->user())
            ->where('provider_key', $providerKey)
            ->firstOrFail();
        Gate::authorize('delete', $credential);

        SourceImportStep::query()
            ->where('provider_key', $providerKey)
            ->where('user_id', $request->user()->id)
            ->delete();
        $credential->delete();

        return back()->with('status', 'Provider token and scheduled step deleted.');
    }
}
