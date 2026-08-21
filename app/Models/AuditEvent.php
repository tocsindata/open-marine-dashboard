<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Models/AuditEvent.php
 * Date: 2026-08-19
 * Copyright:
 *   Copyright (c) 2026 Tocsin Data / Global Incident Map.
 *   All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasUlids;

    private const RESTRICTED_METADATA_KEYS = [
        'accuracy_m',
        'authorization',
        'cookie',
        'coordinates',
        'encrypted_token',
        'lat',
        'latitude',
        'lng',
        'lon',
        'longitude',
        'password',
        'payload',
        'request_body',
        'request_url',
        'response_body',
        'secret',
        'session',
        'token',
    ];

    public $timestamps = false;

    protected $fillable = ['actor_id', 'channel', 'action', 'target_type', 'target_id', 'result', 'metadata', 'occurred_at'];

    public function setMetadataAttribute(?array $metadata): void
    {
        if ($metadata === null) {
            $this->attributes['metadata'] = null;

            return;
        }

        $this->assertMetadataIsSafe($metadata);
        $this->attributes['metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    private function assertMetadataIsSafe(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (is_string($key)) {
                $normalizedKey = str_replace(['-', ' '], '_', mb_strtolower($key));
                if (in_array($normalizedKey, self::RESTRICTED_METADATA_KEYS, true)) {
                    throw new \InvalidArgumentException('Audit metadata contains a restricted field.');
                }
            }

            if (is_array($value)) {
                $this->assertMetadataIsSafe($value);
            }
        }
    }
}
