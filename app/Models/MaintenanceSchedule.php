<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceSchedule extends Model
{
    use HasUlids;

    protected $fillable = [
        'equipment_item_id',
        'name',
        'basis',
        'interval_days',
        'interval_hours',
        'annual_month',
        'annual_day',
        'leap_day_policy',
        'source_kind',
        'source_title',
        'source_reference',
        'source_revision',
        'source_section',
        'source_published_on',
        'source_url',
        'reminder_enabled',
        'reminder_lead_days',
        'reminder_lead_hours',
        'baseline_on',
        'baseline_hours',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'interval_hours' => 'decimal:1',
            'baseline_on' => 'date',
            'source_published_on' => 'date',
            'reminder_enabled' => 'boolean',
            'reminder_lead_hours' => 'decimal:1',
            'baseline_hours' => 'decimal:1',
        ];
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'vessel',
            fn (Builder $vessels): Builder => $vessels->where('owner_id', $user->getKey()),
        );
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(MaintenanceCompletion::class);
    }

    public function reminderOccurrences(): HasMany
    {
        return $this->hasMany(MaintenanceReminderOccurrence::class);
    }

    public function equipmentItem(): BelongsTo
    {
        return $this->belongsTo(VesselEquipmentItem::class, 'equipment_item_id');
    }
}
