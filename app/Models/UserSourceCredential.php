<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSourceCredential extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'provider_key', 'encrypted_token', 'token_hint', 'label',
        'default_latitude', 'default_longitude', 'interval_minutes', 'enabled',
        'last_tested_at', 'last_test_result', 'last_test_message',
    ];

    protected $hidden = ['encrypted_token'];

    protected function casts(): array
    {
        return [
            'encrypted_token' => 'encrypted',
            'enabled' => 'boolean',
            'default_latitude' => 'decimal:7',
            'default_longitude' => 'decimal:7',
            'last_tested_at' => 'datetime',
        ];
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getKey());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
