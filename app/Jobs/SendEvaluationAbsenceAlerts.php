<?php

namespace App\Jobs;

use App\Models\EvaluationAbsenceAlertRun;
use App\Services\Admin\EvaluationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendEvaluationAbsenceAlerts implements ShouldQueue
{
    use Queueable;

    public int $timeout = EvaluationAbsenceAlertRun::TIMEOUT_SECONDS;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $runId)
    {
        $this->onConnection('attendance_alerts');
        $this->onQueue('attendance-alerts');
    }

    public function handle(EvaluationService $evaluations): void
    {
        $claimed = EvaluationAbsenceAlertRun::query()
            ->whereKey($this->runId)
            ->where('status', EvaluationAbsenceAlertRun::STATUS_QUEUED)
            ->update([
                'status' => EvaluationAbsenceAlertRun::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

        if (! $claimed) {
            return;
        }

        $run = EvaluationAbsenceAlertRun::query()->with(['requester', 'evaluation'])->findOrFail($this->runId);
        $previousDriver = Auth::getDefaultDriver();
        $guard = Auth::guard('web');
        $previousUser = $guard->user();

        try {
            $actor = $run->requester;
            if ($actor === null || ! $actor->can('evaluations.update')) {
                throw new RuntimeException(__('evaluations.absence_alerts_permission_changed'));
            }

            // The scope service reads Auth; a worker must use the requester,
            // rather than the unrestricted anonymous CLI context.
            Auth::shouldUse('web');
            $guard->setUser($actor);

            $result = $evaluations->sendAbsenceAlerts($run->evaluation);
            $run->update([
                'status' => EvaluationAbsenceAlertRun::STATUS_COMPLETED,
                'result' => $result,
                'finished_at' => now(),
            ]);
        } finally {
            if ($previousUser !== null) {
                $guard->setUser($previousUser);
            } else {
                $guard->forgetUser();
            }
            Auth::shouldUse($previousDriver);
        }
    }

    public function failed(?Throwable $exception): void
    {
        EvaluationAbsenceAlertRun::query()
            ->whereKey($this->runId)
            ->whereIn('status', [
                EvaluationAbsenceAlertRun::STATUS_QUEUED,
                EvaluationAbsenceAlertRun::STATUS_PROCESSING,
            ])
            ->update([
                'status' => EvaluationAbsenceAlertRun::STATUS_FAILED,
                'error' => __('evaluations.absence_alerts_interrupted'),
                'finished_at' => now(),
            ]);

        Log::warning('Evaluation attendance alert delivery stopped.', [
            'run_id' => $this->runId,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
