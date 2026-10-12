<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationAbsenceAlertRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TIMEOUT_SECONDS = 21_600;

    protected $fillable = [
        'evaluation_id', 'requested_by', 'status', 'result', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_id' => 'int',
            'requested_by' => 'int',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function expireIfStale(): void
    {
        if ($this->status !== self::STATUS_PROCESSING
            || $this->started_at === null
            || $this->started_at->gt(now()->subSeconds(self::TIMEOUT_SECONDS + 60))) {
            return;
        }

        static::query()->whereKey($this->id)->where('status', self::STATUS_PROCESSING)->update([
            'status' => self::STATUS_FAILED,
            'error' => __('evaluations.absence_alerts_interrupted'),
            'finished_at' => now(),
        ]);
        $this->refresh();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'id' => (int) $this->id,
            'status' => $this->status,
            'status_url' => route('admin.evaluations.absence-alerts.status', [
                'evaluation' => $this->evaluation_id,
                'run' => $this->id,
            ]),
            'result' => $this->result,
            'error' => $this->error,
        ];
    }
}
