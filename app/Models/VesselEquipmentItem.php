<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VesselEquipmentItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'category',
        'name',
        'manufacturer',
        'model',
        'serial_number',
        'installed_on',
        'last_serviced_on',
        'warranty_expires_on',
        'meter_hours',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'installed_on' => 'date',
            'last_serviced_on' => 'date',
            'warranty_expires_on' => 'date',
            'meter_hours' => 'decimal:1',
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
}
