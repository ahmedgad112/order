<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementLog extends Model
{
    protected $fillable = [
        'user_id',
        'text',
        'voice',
        'rate',
        'audio_filename',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
