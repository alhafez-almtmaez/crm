<?php

namespace App\Services\Admin;

use App\Models\Homework;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Services\System\CertificateAchievementDateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class HomeworkCertificateEligibilityService
{
    public function __construct(
        private readonly AdminDataScopeService $dataScope,
        private readonly CertificateAchievementDateService $achievementDates,
    ) {}

    /**
     * Use the same first completion evidence as the certificate achievement date.
     * Only include checkpoints whose evidence belongs to the selected homework.
     *
     * @return array<int, array{transaction: StudentPointTransaction, checkpoint: PlanPoint, student: Student}>
     */
    public function targets(Homework $homework): array
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        $transactions = StudentPointTransaction::query()
            ->select('student_point_transactions.*')
            ->join(
                'students as homework_certificate_students',
                'homework_certificate_students.id',
                '=',
                'student_point_transactions.student_id',
            )
            ->join(
                'plan_points as homework_certificate_evidence_points',
                'homework_certificate_evidence_points.id',
                '=',
                'student_point_transactions.plan_point_id',
            )
            ->where('student_point_transactions.homework_id', $homework->id)
            ->where('student_point_transactions.type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->where('homework_certificate_students.is_active', Student::STATUS_ACTIVE)
            ->tap(fn (Builder $query) => $this->dataScope->applyStudentAccess(
                $query,
                'homework_certificate_students',
            ))
            ->with([
                'student:id,full_name',
                'planPoint:id,plan_id',
            ])
            ->orderBy('homework_certificate_students.id')
            ->orderBy('homework_certificate_evidence_points.plan_id')
            ->orderBy('student_point_transactions.created_at')
            ->orderBy('student_point_transactions.id')
            ->get()
            ->filter(static fn (StudentPointTransaction $transaction): bool => $transaction->student instanceof Student
                && $transaction->planPoint instanceof PlanPoint
                && (int) $transaction->planPoint->plan_id > 0
            )
            ->values();

        if ($transactions->isEmpty()) {
            return [];
        }

        $planIds = $transactions
            ->map(static fn (StudentPointTransaction $transaction): int => (int) $transaction->planPoint?->plan_id)
            ->filter()
            ->unique()
            ->values();
        $checkpointsByPlan = PlanPoint::query()
            ->whereIn('plan_id', $planIds)
            ->where('requires_certificate', true)
            ->with('plan:id,category')
            ->orderBy('plan_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(static fn (PlanPoint $checkpoint): int => (int) $checkpoint->plan_id);
        $transactionsByStudentPlan = $transactions->groupBy(
            fn (StudentPointTransaction $transaction): string => $this->studentPlanKey(
                (int) $transaction->student_id,
                (int) $transaction->planPoint?->plan_id,
            ),
        );
        $targets = [];

        foreach ($transactionsByStudentPlan as $planTransactions) {
            /** @var EloquentCollection<int, StudentPointTransaction> $planTransactions */
            $firstTransaction = $planTransactions->first();
            if (! $firstTransaction instanceof StudentPointTransaction) {
                continue;
            }

            /** @var EloquentCollection<int, PlanPoint>|null $checkpoints */
            $checkpoints = $checkpointsByPlan->get((int) $firstTransaction->planPoint?->plan_id);
            if (! $checkpoints instanceof EloquentCollection) {
                continue;
            }

            $firstEvidenceByCheckpoint = $this->achievementDates->earliestEvidenceForCheckpoints(
                $firstTransaction->student,
                $checkpoints,
            );

            foreach ($checkpoints as $checkpoint) {
                $evidence = $firstEvidenceByCheckpoint[(int) $checkpoint->id] ?? null;
                if ($evidence === null || $evidence->homework_id !== (int) $homework->id) {
                    continue;
                }

                $targets[] = [
                    'transaction' => $evidence,
                    'checkpoint' => $checkpoint,
                    'student' => $firstTransaction->student,
                ];
            }
        }

        usort($targets, static fn (array $first, array $second): int => [
            (int) $first['transaction']->student_id,
            (int) $first['checkpoint']->plan_id,
            (int) $first['checkpoint']->sort_order,
            (int) $first['checkpoint']->id,
        ] <=> [
            (int) $second['transaction']->student_id,
            (int) $second['checkpoint']->plan_id,
            (int) $second['checkpoint']->sort_order,
            (int) $second['checkpoint']->id,
        ]);

        return $targets;
    }

    private function studentPlanKey(int $studentId, int $planId): string
    {
        return "{$studentId}:{$planId}";
    }
}
