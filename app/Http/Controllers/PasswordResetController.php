<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public const REQUEST_STATUS = 'If an account matches that email address, a password reset link has been sent.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);

        Password::sendResetLink([
            'email' => mb_strtolower($data['email']),
            'is_active' => true,
        ]);

        // Known and unknown addresses receive the same response to prevent enumeration.
        return back()->with('status', self::REQUEST_STATUS);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => mb_strtolower((string) $request->query('email', '')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        $status = Password::reset(
            [
                'email' => mb_strtolower($data['email']),
                'is_active' => true,
                'password' => $data['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                AuditEvent::create([
                    'actor_id' => $user->id,
                    'channel' => 'web',
                    'action' => 'auth.password_reset',
                    'target_type' => User::class,
                    'target_id' => (string) $user->id,
                    'occurred_at' => now(),
                ]);
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Your password has been reset. You may now log in.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'This password reset link is invalid or has expired. Request a new link.']);
    }
}
