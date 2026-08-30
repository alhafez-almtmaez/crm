<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', static function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('student_gender')->index();
        });

        $centers = DB::table('centers')->pluck('id', 'name');

        $peaceId = $centers->get('مركز السلام القرآني')
            ?? $centers->get('مركز السلام القرآني - مجموعة س');
        $womenId = $centers->get('مشروع الحافظ المتميز - النساء');
        $projectId = $centers->get('مشروع الحافظ المتميز')
            ?? $centers->get('مشروع الحافظ المتميز \\ البناء والإرتقاء');
        $saleheenId = $centers->get('دار القرآن مسجد "الصالحين"');

        if ($peaceId !== null) {
            DB::table('centers')->where('id', $peaceId)->update([
                'name' => 'مركز السلام القرآني',
                'archived_at' => null,
            ]);
        }

        if ($projectId !== null) {
            DB::table('centers')->where('id', $projectId)->update([
                'name' => 'مشروع الحافظ المتميز',
                'archived_at' => null,
            ]);
        }

        $sourceToTarget = [];

        $this->mapCenters($sourceToTarget, $centers, $peaceId, [
            'مركز السلام القرآني - مجموعة س',
            'مركز السلام القرآني - المجموعة الأولى',
            'مركز السلام القرآني - المجموعة الثانية',
            'مركز السلام القرآني | المجموعة الأولى | إقراء',
            'مركز السلام القرآني | المجموعة الثانية | إقراء',
            'الحفاظ',
        ]);
        $this->mapCenters($sourceToTarget, $centers, $womenId, [
            'مشروع الحافظ المتميز - النساء',
        ]);
        $this->mapCenters($sourceToTarget, $centers, $projectId, [
            'مشروع الحافظ المتميز \\ البناء والإرتقاء',
            'مشروع الحافظ المتميز \\ الأشبال',
            'مشروع الحافظ المتميز \\ التميز',
        ]);
        $this->mapCenters($sourceToTarget, $centers, $saleheenId, [
            'دار القرآن مسجد "الصالحين"',
        ]);

        $this->assertAbsenceRuleMoveIsSafe($sourceToTarget);

        $groupNames = [
            'مجموعة س' => 'مجموعة س',
            'المجموعة الأولى' => 'المجموعة الأولى',
            'الأولى | إقراء' => 'المجموعة الأولى إقراء',
            'المجموعة الأولى إقراء' => 'المجموعة الأولى إقراء',
            'المجموعة الثانية' => 'المجموعة الثانية',
            'المجموعة الثانية | إقراء' => 'المجموعة الثانية إقراء',
            'المجموعة الثانية إقراء' => 'المجموعة الثانية إقراء',
            'النساء' => 'مجموعة النساء',
            'مجموعة النساء' => 'مجموعة النساء',
            'التميز' => 'مجموعة التميز',
            'مجموعة التميز' => 'مجموعة التميز',
            'الأشبال' => 'مجموعة الأشبال',
            'مجموعة الأشبال' => 'مجموعة الأشبال',
            'البناء والارتقاء' => 'مجموعة البناء والارتقاء',
            'مجموعة البناء والارتقاء' => 'مجموعة البناء والارتقاء',
            'دار القرآن مسجد " الصالحين"' => 'مجموعة دار قرآن "الصالحين"',
            'مجموعة دار قرآن "الصالحين"' => 'مجموعة دار قرآن "الصالحين"',
        ];

        $this->assertGroupMoveIsSafe($sourceToTarget, $groupNames);

        DB::table('groups')
            ->orderBy('id')
            ->get(['id', 'name', 'center_id'])
            ->each(static function ($group) use ($groupNames, $sourceToTarget): void {
                DB::table('groups')->where('id', $group->id)->update([
                    'name' => $groupNames[$group->name] ?? $group->name,
                    'center_id' => $sourceToTarget[(int) $group->center_id] ?? $group->center_id,
                ]);
            });

        $this->repairStudentsWithoutGroups($sourceToTarget);
        $this->moveCenterReferences($sourceToTarget);

        $now = now();
        foreach ($sourceToTarget as $sourceId => $targetId) {
            if ($sourceId === $targetId) {
                continue;
            }

            DB::table('centers')->where('id', $sourceId)->update(['archived_at' => $now]);
        }
    }

    public function down(): void
    {
        throw new LogicException(
            'The normalized center/group ownership migration is intentionally irreversible. Restore the pre-migration backup instead.'
        );
    }

    /**
     * @param  array<int, int>  $sourceToTarget
     * @param  iterable<string>  $sourceNames
     */
    private function mapCenters(array &$sourceToTarget, $centers, mixed $targetId, array $sourceNames): void
    {
        if ($targetId === null) {
            return;
        }

        foreach ($sourceNames as $sourceName) {
            $sourceId = $centers->get($sourceName);
            if ($sourceId !== null) {
                $sourceToTarget[(int) $sourceId] = (int) $targetId;
            }
        }
    }

    /** @param array<int, int> $sourceToTarget */
    private function repairStudentsWithoutGroups(array $sourceToTarget): void
    {
        $groupsBySourceCenter = DB::table('groups')
            ->get(['id', 'center_id'])
            ->groupBy(static fn ($group): int => (int) $group->center_id);

        DB::table('students')
            ->whereNull('group_id')
            ->whereNotNull('center_id')
            ->orderBy('id')
            ->get(['id', 'center_id'])
            ->each(static function ($student) use ($groupsBySourceCenter, $sourceToTarget): void {
                $targetCenterId = $sourceToTarget[(int) $student->center_id] ?? (int) $student->center_id;
                $candidateGroups = $groupsBySourceCenter->get($targetCenterId, collect());

                if ($candidateGroups->count() !== 1) {
                    return;
                }

                $groupId = (int) $candidateGroups->first()->id;
                DB::table('students')->where('id', $student->id)->update(['group_id' => $groupId]);
                DB::table('group_student')->insertOrIgnore([
                    'group_id' => $groupId,
                    'student_id' => (int) $student->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /** @param array<int, int> $sourceToTarget */
    private function moveCenterReferences(array $sourceToTarget): void
    {
        foreach ($sourceToTarget as $sourceId => $targetId) {
            if ($sourceId === $targetId) {
                continue;
            }

            foreach (['students', 'monthly_plans', 'student_monthly_plans', 'absence_rule_execution_logs'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'center_id')) {
                    DB::table($table)->where('center_id', $sourceId)->update(['center_id' => $targetId]);
                }
            }

            if (Schema::hasTable('absence_rules')) {
                DB::table('absence_rules')->where('center_id', $sourceId)->update(['center_id' => $targetId]);
            }
        }

        DB::table('evaluations')
            ->whereNotNull('group_id')
            ->orderBy('id')
            ->get(['id', 'group_id'])
            ->each(static function ($evaluation): void {
                $centerId = DB::table('groups')->where('id', $evaluation->group_id)->value('center_id');

                if ($centerId !== null) {
                    DB::table('evaluations')->where('id', $evaluation->id)->update(['center_id' => $centerId]);
                }
            });

    }

    /** @param array<int, int> $sourceToTarget */
    private function assertAbsenceRuleMoveIsSafe(array $sourceToTarget): void
    {
        if (! Schema::hasTable('absence_rules')) {
            return;
        }

        $sourceIdsByTarget = [];
        foreach ($sourceToTarget as $sourceId => $targetId) {
            $sourceIdsByTarget[$targetId][] = $sourceId;
        }

        foreach ($sourceIdsByTarget as $targetId => $sourceIds) {
            $centerIds = collect($sourceIds)
                ->map(static fn ($sourceId): int => (int) $sourceId)
                ->push((int) $targetId)
                ->unique()
                ->values()
                ->all();

            $duplicate = DB::table('absence_rules')
                ->whereIn('center_id', $centerIds)
                ->select(['attendance_type', 'occurrence_number'])
                ->selectRaw('COUNT(*) AS aggregate')
                ->groupBy('attendance_type', 'occurrence_number')
                ->havingRaw('COUNT(*) > 1')
                ->first();

            if ($duplicate !== null) {
                throw new RuntimeException(sprintf(
                    'Cannot consolidate center-specific absence rules into center %s: duplicate %s rule for occurrence %s.',
                    (string) $targetId,
                    (string) $duplicate->attendance_type,
                    (string) $duplicate->occurrence_number,
                ));
            }
        }
    }

    /**
     * @param  array<int, int>  $sourceToTarget
     * @param  array<string, string>  $groupNames
     */
    private function assertGroupMoveIsSafe(array $sourceToTarget, array $groupNames): void
    {
        $planned = [];

        foreach (DB::table('groups')->orderBy('id')->get(['id', 'name', 'center_id']) as $group) {
            $targetCenterId = $sourceToTarget[(int) $group->center_id] ?? (int) $group->center_id;
            $targetName = $groupNames[$group->name] ?? $group->name;
            $key = $targetCenterId."\0".$targetName;

            if (isset($planned[$key])) {
                throw new RuntimeException(sprintf(
                    'Cannot consolidate groups %s and %s into center %s with the same name [%s].',
                    (string) $planned[$key],
                    (string) $group->id,
                    (string) $targetCenterId,
                    (string) $targetName,
                ));
            }

            $planned[$key] = (int) $group->id;
        }
    }
};
