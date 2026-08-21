<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Console/Commands/VerifyUserEmail.php
 * Date: 2026-08-19
 * SPDX-License-Identifier: AGPL-3.0-only
 * Copyright (c) 2026 Tocsin Data.
 */

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;

class VerifyUserEmail extends Command
{
    protected $signature = 'marine:verify-user
        {email : Exact email address of the user to verify}
        {--yes : Confirm the change without an interactive prompt}';

    protected $description = 'Mark one existing user email as verified from the terminal';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::INVALID;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user exists with the exact email address {$email}.");

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info("{$email} is already verified. No change was made.");

            return self::SUCCESS;
        }

        if (! $this->option('yes')
            && ! $this->confirm("Mark {$email} as verified?", false)) {
            $this->warn('No change was made.');

            return self::SUCCESS;
        }

        $user->markEmailAsVerified();

        AuditEvent::create([
            'actor_id' => null,
            'channel' => 'terminal',
            'action' => 'email.manually_verified',
            'target_type' => User::class,
            'target_id' => (string) $user->id,
            'result' => 'success',
            'metadata' => ['email' => $email],
            'occurred_at' => now(),
        ]);

        $this->info("{$email} has been verified.");

        return self::SUCCESS;
    }
}
