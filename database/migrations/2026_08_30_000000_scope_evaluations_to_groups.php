<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', static function (Blueprint $table): void {
            $table->string('group_serialized')->nullable()->after('center_id');
            $table->json('working_days')->nullable()->after('group_serialized');
        });

        Schema::table('evaluations', static function (Blueprint $table): void {
            $table->foreignId('group_id')
                ->nullable()
                ->after('center_id')
                ->constrained('groups')
                ->nullOnDelete();
        });

        $this->copyLegacyGroupSettings();
        $this->backfillEvaluationGroups();
        $this->backfillMissingStudentGroups();
        $this->assertEvaluationBackfillIsSafe();

        Schema::table('evaluations', static function (Blueprint $table): void {
            $table->unique(['group_id', 'date'], 'evaluations_group_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', static function (Blueprint $table): void {
            $table->dropUnique('evaluations_group_date_unique');
            $table->dropConstrainedForeignId('group_id');
        });

        Schema::table('groups', static function (Blueprint $table): void {
            $table->dropColumn(['group_serialized', 'working_days']);
        });
    }

    private function copyLegacyGroupSettings(): void
    {
        DB::table('groups')
            ->join('centers', 'groups.center_id', '=', 'centers.id')
            ->select([
                'groups.id',
                'centers.group_serialized',
                'centers.working_days',
            ])
            ->orderBy('groups.id')
            ->each(static function ($row): void {
                DB::table('groups')
                    ->where('id', $row->id)
                    ->update([
                        'group_serialized' => $row->group_serialized,
                        'working_days' => $row->working_days,
                    ]);
            });
    }

    private function backfillEvaluationGroups(): void
    {
        $legacyGroupNames = [
            'مركز السلام القرآني - مجموعة س' => ['مجموعة س'],
            'مركز السلام القرآني - المجموعة الأولى' => ['المجموعة الأولى'],
            'مركز السلام القرآني - المجموعة الثانية' => ['المجموعة الثانية'],
            'مركز السلام القرآني | المجموعة الأولى | إقراء' => ['الأولى | إقراء', 'المجموعة الأولى إقراء'],
            'مركز السلام القرآني | المجموعة الثانية | إقراء' => ['المجموعة الثانية | إقراء', 'المجموعة الثانية إقراء'],
            'مشروع الحافظ المتميز - النساء' => ['النساء', 'مجموعة النساء'],
            'الحفاظ' => ['الحفاظ'],
            'مشروع الحافظ المتميز \\ البناء والإرتقاء' => ['البناء والارتقاء', 'مجموعة البناء والارتقاء'],
            'مشروع الحافظ المتميز \\ الأشبال' => ['الأشبال', 'مجموعة الأشبال'],
            'مشروع الحافظ المتميز \\ التميز' => ['التميز', 'مجموعة التميز'],
            'دار القرآن مسجد "الصالحين"' => ['دار القرآن مسجد " الصالحين"', 'مجموعة دار قرآن "الصالحين"'],
        ];

        $centers = DB::table('centers')->orderBy('id')->get(['id', 'name']);

        foreach ($centers as $center) {
            $groupQuery = DB::table('groups')->where('center_id', $center->id);
            $groupId = null;

            foreach ($legacyGroupNames[$center->name] ?? [] as $groupName) {
                $groupId = (clone $groupQuery)->where('name', $groupName)->value('id');

                if ($groupId !== null) {
                    break;
                }
            }

            if ($groupId === null && (clone $groupQuery)->count() === 1) {
                $groupId = (clone $groupQuery)->value('id');
            }

            if ($groupId === null) {
                continue;
            }

            DB::table('evaluations')
                ->where('center_id', $center->id)
                ->whereNull('group_id')
                ->update(['group_id' => $groupId]);
        }
    }

    private function backfillMissingStudentGroups(): void
    {
        if (! Schema::hasTable('group_student')) {
            return;
        }

        $now = now();

        DB::table('students')
            ->whereNull('group_id')
            ->whereNotNull('center_id')
            ->orderBy('id')
            ->get(['id', 'center_id'])
            ->each(static function ($student) use ($now): void {
                $groups = DB::table('groups')
                    ->where('center_id', $student->center_id)
                    ->pluck('id');

                if ($groups->count() !== 1) {
                    return;
                }

                $groupId = (int) $groups->first();
                DB::table('students')->where('id', $student->id)->update(['group_id' => $groupId]);
                DB::table('group_student')->insertOrIgnore([
                    'group_id' => $groupId,
                    'student_id' => (int) $student->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    private function assertEvaluationBackfillIsSafe(): void
    {
        $unmappedIds = DB::table('evaluations')
            ->whereNull('group_id')
            ->orderBy('id')
            ->limit(20)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($unmappedIds !== []) {
            throw new RuntimeException(
                'Cannot migrate evaluations to groups. Unmapped evaluation IDs: '.implode(', ', $unmappedIds),
            );
        }

        $duplicate = DB::table('evaluations')
            ->select(['group_id', 'date'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('group_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(sprintf(
                'Cannot enforce one evaluation per group and date. Duplicate found for group %s on %s.',
                (string) $duplicate->group_id,
                (string) $duplicate->date,
            ));
        }
    }
};
