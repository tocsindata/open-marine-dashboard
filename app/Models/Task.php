<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Models/Task.php
 * Date: 2026-08-19
 * Copyright:
 *   Copyright (c) 2026 Tocsin Data / Global Incident Map.
 *   All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasUlids;

    protected $fillable = ['vessel_id', 'title', 'description', 'priority', 'status', 'due_at', 'completed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
