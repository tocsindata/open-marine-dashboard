<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vessel extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['name', 'type', 'make', 'model', 'model_year', 'home_port', 'length_m', 'beam_m', 'draft_m', 'registration_number', 'mmsi', 'call_sign', 'status', 'notes'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function equipmentItems(): HasMany
    {
        return $this->hasMany(VesselEquipmentItem::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function maintenanceReminderOccurrences(): HasManyThrough
    {
        return $this->hasManyThrough(
            MaintenanceReminderOccurrence::class,
            MaintenanceSchedule::class,
            'vessel_id',
            'maintenance_schedule_id',
        );
    }

    public function positions(): HasMany
    {
        return $this->hasMany(VesselPosition::class);
    }
}
