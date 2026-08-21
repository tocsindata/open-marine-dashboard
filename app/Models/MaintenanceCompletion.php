<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenanceCompletion extends Model
{
    use HasUlids;

    protected $fillable = ['completed_on', 'meter_hours', 'notes'];

    protected function casts(): array
    {
        return [
            'completed_on' => 'date',
            'meter_hours' => 'decimal:1',
        ];
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'schedule.vessel',
            fn (Builder $vessels): Builder => $vessels->where('owner_id', $user->getKey()),
        );
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(MaintenanceCompletionReversal::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }
}
