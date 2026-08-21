<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const UNIT_SYSTEMS = [
        'metric' => 'Metric',
        'imperial' => 'Imperial / U.S. customary',
    ];

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'timezones' => $this->timezones(),
            'unitSystems' => self::UNIT_SYSTEMS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', Rule::in($this->timezones())],
            'unit_system' => ['required', 'string', Rule::in(array_keys(self::UNIT_SYSTEMS))],
        ]);

        $user = $request->user();
        $user->fill($data);

        if (! $user->isDirty()) {
            return back()->with('status', 'Profile preferences are already current.');
        }

        $user->save();

        AuditEvent::create([
            'actor_id' => $user->id,
            'channel' => 'web',
            'action' => 'account.profile_updated',
            'target_type' => User::class,
            'target_id' => (string) $user->id,
            'occurred_at' => now(),
        ]);

        return back()->with('status', 'Profile preferences saved.');
    }

    /**
     * @return list<string>
     */
    private function timezones(): array
    {
        $timezones = [...DateTimeZone::listIdentifiers(), 'UTC'];
        sort($timezones, SORT_STRING);

        return array_values(array_unique($timezones));
    }
}
