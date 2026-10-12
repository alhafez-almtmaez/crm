<?php

namespace App\Services\Admin;

use App\Jobs\SendEvaluationAbsenceAlerts;
use App\Models\Evaluation;
use App\Models\EvaluationAbsenceAlertRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EvaluationAbsenceAlertDispatchService
{
    public function __construct(private readonly AdminDataScopeService $dataScope) {}

    public function dispatch(Evaluation $evaluation): EvaluationAbsenceAlertRun
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);
        $actorId = Auth::id();
        abort_if($actorId === null, 403);

        return DB::transaction(function () use ($evaluation, $actorId): EvaluationAbsenceAlertRun {
            Evaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();
            $active = EvaluationAbsenceAlertRun::query()
                ->where('evaluation_id', $evaluation->id)
                ->where('requested_by', $actorId)
                ->whereIn('status', [
                    EvaluationAbsenceAlertRun::STATUS_QUEUED,
                    EvaluationAbsenceAlertRun::STATUS_PROCESSING,
                ])
                ->latest('id')
                ->first();

            if ($active !== null) {
                $active->expireIfStale();
                if ($active->status !== EvaluationAbsenceAlertRun::STATUS_FAILED) {
                    return $active;
                }
            }

            $run = EvaluationAbsenceAlertRun::query()->create([
                'evaluation_id' => $evaluation->id,
                'requested_by' => $actorId,
                'status' => EvaluationAbsenceAlertRun::STATUS_QUEUED,
            ]);

            // This dedicated database connection uses the application's DB, so
            // inserting the job and its run are committed atomically together.
            SendEvaluationAbsenceAlerts::dispatch((int) $run->id);

            return $run;
        });
    }

    /**
     * @param  array<int, int>  $evaluationIds
     * @return array<int, array<string, mixed>>
     */
    public function activeRuns(array $evaluationIds): array
    {
        return EvaluationAbsenceAlertRun::query()
            ->whereIn('evaluation_id', $evaluationIds)
            ->where('requested_by', Auth::id())
            ->whereIn('status', [
                EvaluationAbsenceAlertRun::STATUS_QUEUED,
                EvaluationAbsenceAlertRun::STATUS_PROCESSING,
            ])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (EvaluationAbsenceAlertRun $run): array => [
                $run->evaluation_id => $run->payload(),
            ])
            ->all();
    }
}
