<?php

namespace App\Services\Admin\AbsenceRules;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class AbsenceAlertExecutionLock
{
    public function acquire(int $evaluationId): ?Lock
    {
        $lock = Cache::lock($this->key($evaluationId), $this->lockSeconds());

        return $lock->get() ? $lock : null;
    }

    public function refresh(Lock $lock): bool
    {
        if (! method_exists($lock, 'refresh')) {
            return false;
        }

        return (bool) $lock->refresh($this->lockSeconds());
    }

    public function key(int $evaluationId): string
    {
        return "absence-alerts:evaluation:{$evaluationId}";
    }

    private function lockSeconds(): int
    {
        $messageDelay = max(0, (int) config('services.whatsapp_api.message_delay_seconds', 30));

        // A single student can target two phone numbers plus the group. Leave
        // room for delays, HTTP timeouts, a session retry, and database work;
        // the engine refreshes this lease before every subsequent student.
        return max(600, ($messageDelay * 3) + 300);
    }
}
