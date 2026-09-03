<?php

namespace App\Services\System;

use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CertificateAchievementDateService
{
    public function earliestEvidence(
        Student|int $student,
        PlanPoint $checkpoint,
        bool $lockForUpdate = false,
    ): ?StudentPointTransaction {
        $studentId = $student instanceof Student ? (int) $student->getKey() : $student;

        return StudentPointTransaction::query()
            ->select('student_point_transactions.*')
            ->join(
                'plan_points as certificate_evidence_points',
                'certificate_evidence_points.id',
                '=',
                'student_point_transactions.plan_point_id',
            )
            ->where('student_point_transactions.student_id', $studentId)
            ->where('student_point_transactions.type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->whereNotNull('student_point_transactions.created_at')
            ->where('certificate_evidence_points.plan_id', $checkpoint->plan_id)
            ->where(function ($query) use ($checkpoint): void {
                $query->where('certificate_evidence_points.sort_order', '>', $checkpoint->sort_order)
                    ->orWhere(function ($sameOrder) use ($checkpoint): void {
                        $sameOrder
                            ->where('certificate_evidence_points.sort_order', $checkpoint->sort_order)
                            ->where('certificate_evidence_points.id', '>=', $checkpoint->id);
                    });
            })
            ->when($lockForUpdate, static fn ($query) => $query->lockForUpdate())
            ->oldest('student_point_transactions.created_at')
            ->oldest('student_point_transactions.id')
            ->first();
    }

    /**
     * Resolve all reached checkpoint dates using one evidence query.
     *
     * @param  EloquentCollection<int, PlanPoint>  $checkpoints
     * @return array<int, StudentPointTransaction>
     */
    public function earliestEvidenceForCheckpoints(Student $student, EloquentCollection $checkpoints): array
    {
        if ($checkpoints->isEmpty()) {
            return [];
        }

        $planIds = $checkpoints
            ->pluck('plan_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $transactions = StudentPointTransaction::query()
            ->with('planPoint:id,plan_id,sort_order')
            ->where('student_id', $student->getKey())
            ->where('type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->whereNotNull('created_at')
            ->whereHas('planPoint', static fn ($query) => $query->whereIn('plan_id', $planIds))
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->groupBy(static fn (StudentPointTransaction $transaction): int => (int) $transaction->planPoint?->plan_id);
        $evidence = [];

        foreach ($checkpoints as $checkpoint) {
            $planTransactions = $transactions->get((int) $checkpoint->plan_id, new EloquentCollection);
            $first = $planTransactions->first(
                fn (StudentPointTransaction $transaction): bool => $this->reaches(
                    $transaction->planPoint,
                    $checkpoint,
                ),
            );

            if ($first instanceof StudentPointTransaction) {
                $evidence[(int) $checkpoint->id] = $first;
            }
        }

        return $evidence;
    }

    private function reaches(?PlanPoint $evidence, PlanPoint $checkpoint): bool
    {
        if ($evidence === null || (int) $evidence->plan_id !== (int) $checkpoint->plan_id) {
            return false;
        }

        return (int) $evidence->sort_order > (int) $checkpoint->sort_order
            || ((int) $evidence->sort_order === (int) $checkpoint->sort_order
                && (int) $evidence->id >= (int) $checkpoint->id);
    }
}
