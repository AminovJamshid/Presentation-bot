<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $primaryKey = 'telegram_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
        'is_blocked',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    // Relationships
    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class, 'user_telegram_id', 'telegram_id');
    }

    public function conversationState()
    {
        return $this->hasOne(ConversationState::class, 'user_telegram_id', 'telegram_id');
    }

    // Helper
    public function updateLastActive(): void
    {
        $this->last_active_at = now();
        $this->save();
    }
}
