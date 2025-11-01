<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presentation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_telegram_id',
        'university',
        'direction',
        'group_name',
        'info_placement',
        'topic',
        'pages_count',
        'format',
        'status',
        'file_path',
        'file_size',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'pages_count' => 'integer',
        'file_size' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_telegram_id', 'telegram_id');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isGenerating()
    {
        return $this->status === 'generating';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function markAsGenerating()
    {
        $this->status = 'generating';
        $this->save();
    }

    public function markAsCompleted($filePath, $fileSize)
    {
        $this->status = 'completed';
        $this->file_path = $filePath;
        $this->file_size = $fileSize;
        $this->completed_at = now();
        $this->save();
    }

    public function markAsFailed($errorMessage)
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->save();
    }
}
