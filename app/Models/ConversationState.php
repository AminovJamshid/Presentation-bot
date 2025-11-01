<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ConversationState extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_telegram_id',
        'current_state',
        'data',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_telegram_id', 'telegram_id');
    }

    public function getData($key = null, $default = null)
    {
        if ($key === null) {
            return $this->data ?? [];
        }
        return $this->data[$key] ?? $default;
    }

    public function setData($key, $value)
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        $this->data = $data;
        $this->save();
    }

    public function setState($state)
    {
        $this->current_state = $state;
        $this->save();
    }

    public function extendExpiry()
    {
        $this->expires_at = Carbon::now()->addMinutes(15);
        $this->save();
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public static function startConversation($telegramId, $initialState = 'IDLE')
    {
        return self::updateOrCreate(
            ['user_telegram_id' => $telegramId],
            [
                'current_state' => $initialState,
                'data' => [],
                'expires_at' => Carbon::now()->addMinutes(15),
            ]
        );
    }

    public function clear()
    {
        $this->current_state = 'IDLE';
        $this->data = [];
        $this->expires_at = null;
        $this->save();
    }
}
