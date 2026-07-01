<?php

namespace App\Services\Admin;

use App\Models\AbsenceRule;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\Group;
use App\Models\Homework;
use App\Models\MonthlyPlan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;

class AdminDataScopeService
{
    public function isAdmin(?User $user = null): bool
    {
        $user ??= Auth::user();

        return (bool) $user?->hasRole('admin');
    }

    public function shouldScope(?User $user = null): bool
    {
        $user ??= Auth::user();
        if ($user === null) {
            return false;
        }

        return ! $this->isAdmin($user);
    }

    public function applyStudentAccess(EloquentBuilder|QueryBuilder $query, string $studentTable = 'students'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query->where("{$studentTable}.admin_id", $userId);
    }

    public function applyCenterAccess(EloquentBuilder|QueryBuilder $query, string $centerTable = 'centers'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query->whereExists(function (QueryBuilder $subQuery) use ($centerTable, $userId): void {
            $subQuery
                ->selectRaw('1')
                ->from('students as scope_students')
                ->whereColumn('scope_students.center_id', "{$centerTable}.id")
                ->where('scope_students.admin_id', $userId);
        });
    }

    public function applyGroupAccess(EloquentBuilder|QueryBuilder $query, string $groupTable = 'groups'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query->whereExists(function (QueryBuilder $subQuery) use ($groupTable, $userId): void {
            $subQuery
                ->selectRaw('1')
                ->from('students as scope_students')
                ->whereColumn('scope_students.group_id', "{$groupTable}.id")
                ->where('scope_students.admin_id', $userId);
        });
    }

    public function applyHomeworkAccess(EloquentBuilder|QueryBuilder $query, string $homeworkTable = 'homeworks'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query->whereExists(function (QueryBuilder $subQuery) use ($homeworkTable, $userId): void {
            $subQuery
                ->selectRaw('1')
                ->from('homework_students as scope_homework_students')
                ->join('students as scope_students', 'scope_homework_students.student_id', '=', 'scope_students.id')
                ->whereColumn('scope_homework_students.homework_id', "{$homeworkTable}.id")
                ->where('scope_students.admin_id', $userId);
        });
    }

    public function applyEvaluationAccess(EloquentBuilder|QueryBuilder $query, string $evaluationTable = 'evaluations'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query->whereExists(function (QueryBuilder $subQuery) use ($evaluationTable, $userId): void {
            $subQuery
                ->selectRaw('1')
                ->from('evaluations_users as scope_evaluation_students')
                ->join('students as scope_students', 'scope_evaluation_students.student_id', '=', 'scope_students.id')
                ->whereColumn('scope_evaluation_students.evaluation_id', "{$evaluationTable}.id")
                ->where('scope_students.admin_id', $userId);
        });
    }

    public function applyMonthlyPlanAccess(EloquentBuilder|QueryBuilder $query, string $monthlyPlanTable = 'monthly_plans'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query->whereExists(function (QueryBuilder $subQuery) use ($monthlyPlanTable, $userId): void {
            $subQuery
                ->selectRaw('1')
                ->from('student_monthly_plans as scope_student_monthly_plans')
                ->join('students as scope_students', 'scope_student_monthly_plans.student_id', '=', 'scope_students.id')
                ->whereColumn('scope_student_monthly_plans.monthly_plan_id', "{$monthlyPlanTable}.id")
                ->where('scope_students.admin_id', $userId);
        });
    }

    public function applyAbsenceRuleAccess(EloquentBuilder|QueryBuilder $query, string $absenceRuleTable = 'absence_rules'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query
            ->whereNotNull("{$absenceRuleTable}.center_id")
            ->whereExists(function (QueryBuilder $subQuery) use ($absenceRuleTable, $userId): void {
                $subQuery
                    ->selectRaw('1')
                    ->from('students as scope_students')
                    ->whereColumn('scope_students.center_id', "{$absenceRuleTable}.center_id")
                    ->where('scope_students.admin_id', $userId);
            });
    }

    public function applyAbsenceExecutionLogAccess(EloquentBuilder|QueryBuilder $query, string $logTable = 'absence_rule_execution_logs'): EloquentBuilder|QueryBuilder
    {
        $userId = $this->scopedUserId();
        if ($userId === null) {
            return $query;
        }

        return $query->whereExists(function (QueryBuilder $subQuery) use ($logTable, $userId): void {
            $subQuery
                ->selectRaw('1')
                ->from('students as scope_students')
                ->whereColumn('scope_students.id', "{$logTable}.student_id")
                ->where('scope_students.admin_id', $userId);
        });
    }

    public function canAccessStudent(Student $student): bool
    {
        $userId = $this->scopedUserId();

        return $userId === null || (int) $student->admin_id === $userId;
    }

    public function canAccessCenter(Center $center): bool
    {
        return $this->centerIdsQuery()->whereKey($center->id)->exists();
    }

    public function canAccessGroup(Group $group): bool
    {
        return $this->groupIdsQuery()->whereKey($group->id)->exists();
    }

    public function canAccessHomework(Homework $homework): bool
    {
        return $this->applyHomeworkAccess(Homework::query())->whereKey($homework->id)->exists();
    }

    public function canAccessEvaluation(Evaluation $evaluation): bool
    {
        return $this->applyEvaluationAccess(Evaluation::query())->whereKey($evaluation->id)->exists();
    }

    public function canAccessMonthlyPlan(MonthlyPlan $monthlyPlan): bool
    {
        return $this->applyMonthlyPlanAccess(MonthlyPlan::query())->whereKey($monthlyPlan->id)->exists();
    }

    public function canAccessAbsenceRule(AbsenceRule $rule): bool
    {
        return $this->applyAbsenceRuleAccess(AbsenceRule::query())->whereKey($rule->id)->exists();
    }

    public function abortUnlessCanAccessStudent(Student $student): void
    {
        abort_unless($this->canAccessStudent($student), 404);
    }

    public function abortUnlessCanAccessCenter(Center $center): void
    {
        abort_unless($this->canAccessCenter($center), 404);
    }

    public function abortUnlessCanAccessGroup(Group $group): void
    {
        abort_unless($this->canAccessGroup($group), 404);
    }

    public function abortUnlessCanAccessHomework(Homework $homework): void
    {
        abort_unless($this->canAccessHomework($homework), 404);
    }

    public function abortUnlessCanAccessEvaluation(Evaluation $evaluation): void
    {
        abort_unless($this->canAccessEvaluation($evaluation), 404);
    }

    public function abortUnlessCanAccessMonthlyPlan(MonthlyPlan $monthlyPlan): void
    {
        abort_unless($this->canAccessMonthlyPlan($monthlyPlan), 404);
    }

    public function abortUnlessCanAccessAbsenceRule(AbsenceRule $rule): void
    {
        abort_unless($this->canAccessAbsenceRule($rule), 404);
    }

    private function centerIdsQuery(): EloquentBuilder
    {
        return $this->applyCenterAccess(Center::query());
    }

    private function groupIdsQuery(): EloquentBuilder
    {
        return $this->applyGroupAccess(Group::query());
    }

    private function scopedUserId(): ?int
    {
        if (! $this->shouldScope()) {
            return null;
        }

        return Auth::id();
    }
}
