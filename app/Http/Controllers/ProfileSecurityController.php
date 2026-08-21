<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileSecurityController extends Controller
{
    private const ACTIVITY_LABELS = [
        'account.profile_updated' => 'Profile preferences updated',
        'account.registered' => 'Account registered',
        'auth.login' => 'Signed in',
        'auth.logout' => 'Signed out',
        'auth.other_sessions_invalidated' => 'Other browser sessions ended',
        'auth.password_reset' => 'Password reset completed',
        'email.auto_verified_local' => 'Email verified locally',
    ];

    public function show(Request $request): View
    {
        $activity = AuditEvent::query()
            ->where('actor_id', $request->user()->id)
            ->whereIn('action', array_keys(self::ACTIVITY_LABELS))
            ->latest('occurred_at')
            ->limit(10)
            ->get(['action', 'occurred_at']);

        return view('profile.security', [
            'activity' => $activity,
            'activityLabels' => self::ACTIVITY_LABELS,
        ]);
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        Auth::logoutOtherDevices($data['password']);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'channel' => 'web',
            'action' => 'auth.other_sessions_invalidated',
            'target_type' => User::class,
            'target_id' => (string) $request->user()->id,
            'occurred_at' => now(),
        ]);

        return back()->with('status', 'Other browser sessions have been ended. This browser remains signed in.');
    }
}
