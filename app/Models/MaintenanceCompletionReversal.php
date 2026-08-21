<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceCompletionReversal extends Model
{
    use HasUlids;

    protected $fillable = ['reason'];

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'completion.schedule.vessel',
            fn (Builder $vessels): Builder => $vessels->where('owner_id', $user->getKey()),
        );
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCompletion::class, 'maintenance_completion_id');
    }
}
