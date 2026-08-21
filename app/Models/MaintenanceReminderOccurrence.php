<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceReminderOccurrence extends Model
{
    use HasUlids;

    protected $fillable = [
        'reference_type',
        'reference_value',
        'status',
        'opened_at',
        'acknowledged_at',
        'suppressed_at',
        'dismissed_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'suppressed_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'schedule.vessel',
            fn (Builder $vessels): Builder => $vessels->where('owner_id', $user->getKey()),
        );
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }
}
