<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppPendingMessage extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    protected $table = 'whatsapp_pending_messages';

    protected $fillable = [
        'chat_ids',
        'content',
        'media_url',
        'source_type',
        'source_id',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'last_attempted_at',
        'sent_at',
    ];

    protected $casts = [
        'chat_ids' => 'array',
        'attempts' => 'int',
        'available_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            });
    }
}
