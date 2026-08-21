<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Http/Controllers/AuthController.php
 * Date: 2026-08-19
 * SPDX-License-Identifier: AGPL-3.0-only
 * Copyright (c) 2026 Tocsin Data.
 */

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
        ]);
        $user = User::create([
            'name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'],
            'policy_version' => '2026-08-19-v1', 'policy_accepted_at' => now(),
        ]);
        $autoVerifiedLocally = app()->environment('local')
            && config('marine.local_auto_verify_email');

        if ($autoVerifiedLocally) {
            $user->markEmailAsVerified();
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        AuditEvent::create(['actor_id' => $user->id, 'channel' => 'web', 'action' => 'account.registered', 'target_type' => User::class, 'target_id' => (string) $user->id, 'occurred_at' => now()]);
        if ($autoVerifiedLocally) {
            AuditEvent::create(['actor_id' => $user->id, 'channel' => 'system', 'action' => 'email.auto_verified_local', 'target_type' => User::class, 'target_id' => (string) $user->id, 'occurred_at' => now()]);
        }

        return redirect()->route($user->hasVerifiedEmail() ? 'dashboard' : 'verification.notice');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt(['email' => mb_strtolower($credentials['email']), 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The supplied credentials could not be verified.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        AuditEvent::create(['actor_id' => Auth::id(), 'channel' => 'web', 'action' => 'auth.login', 'target_type' => User::class, 'target_id' => (string) Auth::id(), 'occurred_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $actor = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        AuditEvent::create(['actor_id' => $actor, 'channel' => 'web', 'action' => 'auth.logout', 'target_type' => User::class, 'target_id' => (string) $actor, 'occurred_at' => now()]);

        return redirect()->route('home');
    }
}
