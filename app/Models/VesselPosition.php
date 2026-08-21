<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VesselPosition extends Model
{
    use HasUlids;

    protected $fillable = ['latitude', 'longitude', 'speed_knots', 'course_degrees', 'accuracy_m', 'source', 'recorded_at'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'speed_knots' => 'float',
            'course_degrees' => 'float',
            'accuracy_m' => 'float',
            'recorded_at' => 'datetime',
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
