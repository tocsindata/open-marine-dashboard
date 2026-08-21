<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Console/Commands/ManageSuperAdmin.php
 * Date: 2026-08-19
 * Copyright:
 *   Copyright (c) 2026 Tocsin Data / Global Incident Map.
 *   All rights reserved.
 */

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ManageSuperAdmin extends Command
{
    protected $signature = 'marine:super-admin {email? : Existing or new account email} {--name= : Account name} {--verify-email : Explicitly mark email verified} {--activate : Activate the account}';

    protected $description = 'Create or update a Marine super administrator from the terminal';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->argument('email') ?: $this->ask('Email'))));
        if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc']])->fails()) {
            $this->error('A valid email is required.');

            return self::INVALID;
        }
        $matches = User::whereRaw('LOWER(email) = ?', [$email])->get();
        if ($matches->count() > 1) {
            $this->error('Ambiguous email match; no changes made.');

            return self::FAILURE;
        }
        $user = $matches->first();
        $name = trim((string) ($this->option('name') ?: ($user?->name ?: $this->ask('Name'))));
        $password = (string) $this->secret($user ? 'New password (leave blank to retain)' : 'Password (minimum 12 characters)');
        if (! $user && mb_strlen($password) < 12) {
            $this->error('A password of at least 12 characters is required.');

            return self::INVALID;
        }
        if (! $this->confirm(($user ? 'Update' : 'Create').' this account and grant super administrator privileges?')) {
            $this->warn('No changes made.');

            return self::SUCCESS;
        }
        $user ??= new User(['email' => $email]);
        $user->name = $name;
        $user->role = 'super_admin';
        if ($password !== '') {
            $user->password = Hash::make($password);
        }
        if ($this->option('activate') || ! $user->exists) {
            $user->is_active = true;
        }
        if ($this->option('verify-email')) {
            $user->email_verified_at = now();
        }
        $user->save();
        AuditEvent::create(['actor_id' => $user->id, 'channel' => 'cli', 'action' => 'super_admin.granted', 'target_type' => User::class, 'target_id' => (string) $user->id, 'metadata' => ['operator' => get_current_user()], 'occurred_at' => now()]);
        $this->info('Super administrator account saved.');

        return self::SUCCESS;
    }
}
