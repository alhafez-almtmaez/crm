<?php

namespace App\Services\Admin;

use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Device;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkStudentPoint;
use App\Models\Plan;
use App\Models\Student;
use App\Services\System\DateTimeFormatterService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private readonly DateTimeFormatterService $dateTimeFormatter)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        return [
            'summary' => $this->summary($today),
            'student_status' => $this->studentStatus(),
            'attendance_trend' => $this->attendanceTrend($today),
            'homework_progress' => $this->homeworkProgress(),
            'center_performance' => $this->centerPerformance($today),
            'alerts' => $this->alerts($today),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summary(CarbonImmutable $today): array
    {
        $lastThirtyStart = $today->subDays(29);
        $monthStart = $today->startOfMonth();

        $attendanceBase = EvaluationStudent::query()
            ->whereIn('attendances', [
                EvaluationStudent::ATTENDANCE_PRESENT,
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
                EvaluationStudent::ATTENDANCE_ABSENCE,
            ])
            ->whereHas('evaluation', function ($query) use ($lastThirtyStart, $today): void {
                $query->whereBetween('date', [$lastThirtyStart->toDateString(), $today->toDateString()]);
            });

        $attendanceTotal = (clone $attendanceBase)->count();
        $attendancePresent = (clone $attendanceBase)
            ->where('attendances', EvaluationStudent::ATTENDANCE_PRESENT)
            ->count();

        $homeworkPointsBase = HomeworkStudentPoint::query()
            ->where('is_done', true)
            ->whereHas('homework', function ($query) use ($monthStart, $today): void {
                $query->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]);
            });

        return [
            'students_total' => Student::query()->count(),
            'active_students' => Student::query()->where('is_active', Student::STATUS_ACTIVE)->count(),
            'frozen_students' => Student::query()->where('is_active', Student::STATUS_FROZEN)->count(),
            'centers_total' => Center::query()->count(),
            'groups_total' => Group::query()->count(),
            'plans_total' => Plan::query()->count(),
            'evaluations_this_month' => Evaluation::query()
                ->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()])
                ->count(),
            'homeworks_this_month' => Homework::query()
                ->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()])
                ->count(),
            'homework_points_completed_month' => (clone $homeworkPointsBase)->count(),
            'homework_points_awarded_month' => (int) (clone $homeworkPointsBase)->sum('awarded_points'),
            'attendance_present_last_30' => $attendancePresent,
            'attendance_total_last_30' => $attendanceTotal,
            'attendance_rate_last_30' => $this->percentage($attendancePresent, $attendanceTotal),
            'whatsapp_connected_devices' => Device::query()->where('status', 'CONNECTED')->count(),
            'whatsapp_devices_total' => Device::query()->count(),
        ];
    }

    /**
     * @return array<int, array{key: string, count: int}>
     */
    private function studentStatus(): array
    {
        return [
            [
                'key' => 'active',
                'count' => Student::query()->where('is_active', Student::STATUS_ACTIVE)->count(),
            ],
            [
                'key' => 'frozen',
                'count' => Student::query()->where('is_active', Student::STATUS_FROZEN)->count(),
            ],
            [
                'key' => 'inactive',
                'count' => Student::query()->where('is_active', Student::STATUS_INACTIVE)->count(),
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, present: array<int, int>, absent: array<int, int>, excused: array<int, int>}
     */
    private function attendanceTrend(CarbonImmutable $today): array
    {
        $dates = collect(range(13, 0))
            ->map(fn (int $daysAgo): CarbonImmutable => $today->subDays($daysAgo));
        $startDate = $dates->first()?->toDateString() ?? $today->toDateString();

        $rows = EvaluationStudent::query()
            ->join('evaluations', 'evaluations_users.evaluation_id', '=', 'evaluations.id')
            ->whereBetween('evaluations.date', [$startDate, $today->toDateString()])
            ->whereIn('evaluations_users.attendances', [
                EvaluationStudent::ATTENDANCE_PRESENT,
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
                EvaluationStudent::ATTENDANCE_ABSENCE,
            ])
            ->get([
                'evaluations.date',
                'evaluations_users.attendances',
            ])
            ->groupBy(fn ($row): string => CarbonImmutable::parse((string) $row->date)->toDateString());

        return [
            'labels' => $dates->map(fn (CarbonImmutable $date): string => $this->shortDateLabel($date))->all(),
            'present' => $dates->map(fn (CarbonImmutable $date): int => $this->attendanceCount(
                $rows->get($date->toDateString(), collect()),
                EvaluationStudent::ATTENDANCE_PRESENT,
            ))->all(),
            'absent' => $dates->map(fn (CarbonImmutable $date): int => $this->attendanceCount(
                $rows->get($date->toDateString(), collect()),
                EvaluationStudent::ATTENDANCE_ABSENCE,
            ))->all(),
            'excused' => $dates->map(fn (CarbonImmutable $date): int => $this->attendanceCount(
                $rows->get($date->toDateString(), collect()),
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
            ))->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function homeworkProgress(): array
    {
        return Homework::query()
            ->with('center:id,name')
            ->withCount([
                'students as students_count',
                'points as total_points_count',
                'points as completed_points_count' => fn ($query) => $query->where('is_done', true),
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->sortBy('date')
            ->values()
            ->map(function (Homework $homework): array {
                $total = (int) ($homework->total_points_count ?? 0);
                $completed = (int) ($homework->completed_points_count ?? 0);

                return [
                    'id' => $homework->id,
                    'label' => $this->shortDateLabel($homework->date),
                    'date' => $homework->date?->format('Y-m-d'),
                    'center_name' => $homework->center?->name ?? '-',
                    'students_count' => (int) ($homework->students_count ?? 0),
                    'completed' => $completed,
                    'pending' => max($total - $completed, 0),
                    'total' => $total,
                    'rate' => $this->percentage($completed, $total),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function centerPerformance(CarbonImmutable $today): array
    {
        $monthStart = $today->startOfMonth();
        $homeworksByCenter = Homework::query()
            ->select('center_id', DB::raw('COUNT(*) as aggregate'))
            ->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()])
            ->groupBy('center_id')
            ->pluck('aggregate', 'center_id');

        return Center::query()
            ->withCount([
                'students as students_count',
                'students as active_students_count' => fn ($query) => $query->where('is_active', Student::STATUS_ACTIVE),
                'groups as groups_count',
                'evaluations as evaluations_count' => function ($query) use ($monthStart, $today): void {
                    $query->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]);
                },
            ])
            ->orderByDesc('students_count')
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name'])
            ->map(fn (Center $center): array => [
                'id' => $center->id,
                'name' => $center->name,
                'students_count' => (int) ($center->students_count ?? 0),
                'active_students_count' => (int) ($center->active_students_count ?? 0),
                'groups_count' => (int) ($center->groups_count ?? 0),
                'evaluations_count' => (int) ($center->evaluations_count ?? 0),
                'homeworks_count' => (int) ($homeworksByCenter[$center->id] ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alerts(CarbonImmutable $today): array
    {
        $lastSevenStart = $today->subDays(6);
        $lastThirtyStart = $today->subDays(29);
        $studentsWithoutPlan = Student::query()->whereNull('plan_type_id')->count();
        $studentsWithoutGroup = Student::query()->whereNull('group_id')->count();
        $failedAbsenceMessages = AbsenceRuleExecutionLog::query()
            ->where('was_message_sent', false)
            ->where(function ($query) use ($lastThirtyStart, $today): void {
                $query
                    ->whereBetween('executed_at', [$lastThirtyStart->startOfDay(), $today->endOfDay()])
                    ->orWhere(function ($nested) use ($lastThirtyStart, $today): void {
                        $nested
                            ->whereNull('executed_at')
                            ->whereBetween('created_at', [$lastThirtyStart->startOfDay(), $today->endOfDay()]);
                    });
            })
            ->count();
        $recentEvaluations = Evaluation::query()
            ->whereBetween('date', [$lastSevenStart->toDateString(), $today->toDateString()])
            ->count();
        $connectedDevices = Device::query()->where('status', 'CONNECTED')->count();
        $totalDevices = Device::query()->count();

        return [
            [
                'key' => 'studentsWithoutPlan',
                'count' => $studentsWithoutPlan,
                'tone' => $studentsWithoutPlan > 0 ? 'warning' : 'success',
                'href' => '/admin/students',
            ],
            [
                'key' => 'studentsWithoutGroup',
                'count' => $studentsWithoutGroup,
                'tone' => $studentsWithoutGroup > 0 ? 'warning' : 'success',
                'href' => '/admin/students',
            ],
            [
                'key' => 'failedAbsenceMessages',
                'count' => $failedAbsenceMessages,
                'tone' => $failedAbsenceMessages > 0 ? 'danger' : 'success',
                'href' => '/admin/activity-logs',
            ],
            [
                'key' => 'recentEvaluations',
                'count' => $recentEvaluations,
                'tone' => $recentEvaluations > 0 ? 'success' : 'warning',
                'href' => '/admin/evaluations',
            ],
            [
                'key' => 'whatsappDevices',
                'count' => $connectedDevices,
                'total' => $totalDevices,
                'tone' => $connectedDevices > 0 ? 'success' : 'warning',
                'href' => '/admin/whatsapp',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function recentActivity(): array
    {
        $evaluations = Evaluation::query()
            ->with('center:id,name')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Evaluation $evaluation): array => [
                'type' => 'evaluation',
                'icon' => 'pi pi-clipboard',
                'title' => $evaluation->center?->name ?? '-',
                'meta' => $this->fullDateLabel($evaluation->date),
                'date' => $this->dateTimeFormatter->formatForAdmin($evaluation->created_at),
                'timestamp' => $evaluation->created_at?->getTimestamp() ?? 0,
                'href' => "/admin/evaluations/{$evaluation->id}/edit",
            ]);

        $homeworks = Homework::query()
            ->with('center:id,name')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Homework $homework): array => [
                'type' => 'homework',
                'icon' => 'pi pi-check-square',
                'title' => $homework->center?->name ?? '-',
                'meta' => $this->fullDateLabel($homework->date),
                'date' => $this->dateTimeFormatter->formatForAdmin($homework->created_at),
                'timestamp' => $homework->created_at?->getTimestamp() ?? 0,
                'href' => "/admin/homeworks/{$homework->id}/edit",
            ]);

        $absenceLogs = AbsenceRuleExecutionLog::query()
            ->with(['student:id,full_name', 'center:id,name'])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn (AbsenceRuleExecutionLog $log): array => [
                'type' => 'absence',
                'icon' => 'pi pi-exclamation-circle',
                'title' => $log->student?->full_name ?? '-',
                'meta' => $log->center?->name ?? '-',
                'date' => $this->dateTimeFormatter->formatForAdmin($log->executed_at ?? $log->created_at),
                'timestamp' => ($log->executed_at ?? $log->created_at)?->getTimestamp() ?? 0,
                'href' => '/admin/activity-logs',
            ]);

        return collect()
            ->merge($evaluations)
            ->merge($homeworks)
            ->merge($absenceLogs)
            ->sortByDesc('timestamp')
            ->take(8)
            ->map(function (array $item): array {
                unset($item['timestamp']);

                return $item;
            })
            ->values()
            ->all();
    }

    private function attendanceCount(Collection $rows, int $attendanceValue): int
    {
        return $rows
            ->filter(fn ($row): bool => (int) $row->attendances === $attendanceValue)
            ->count();
    }

    private function percentage(int $part, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round(($part / $total) * 100);
    }

    private function shortDateLabel(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $this->toImmutableDate($value)
            ->locale(app()->getLocale())
            ->translatedFormat('j M');
    }

    private function fullDateLabel(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $this->toImmutableDate($value)
            ->locale(app()->getLocale())
            ->translatedFormat('l ، j F ، Y');
    }

    private function toImmutableDate(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value->toDateString());
        }

        return CarbonImmutable::parse($value);
    }
}
