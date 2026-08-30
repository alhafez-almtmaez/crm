<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeworks', static function (Blueprint $table): void {
            $table->unsignedBigInteger('group_id')->nullable()->after('center_id');
        });

        $this->backfillGroupsFromLegacyCenters();
        $this->assertBackfillIsCompleteAndUnique();

        Schema::table('homeworks', static function (Blueprint $table): void {
            $table->unsignedBigInteger('group_id')->nullable(false)->change();
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->restrictOnDelete();
        });

        Schema::table('homeworks', static function (Blueprint $table): void {
            $table->dropUnique('homeworks_center_id_date_unique');
            $table->unique(['group_id', 'date'], 'homeworks_group_date_unique');
        });

        DB::table('homeworks')
            ->join('groups', 'homeworks.group_id', '=', 'groups.id')
            ->whereColumn('homeworks.center_id', '!=', 'groups.center_id')
            ->orderBy('homeworks.id')
            ->get(['homeworks.id', 'groups.center_id as group_center_id'])
            ->each(static function ($homework): void {
                DB::table('homeworks')
                    ->where('id', $homework->id)
                    ->update(['center_id' => $homework->group_center_id]);
            });
    }

    public function down(): void
    {
        throw new LogicException(
            'Group-scoped homework ownership is intentionally irreversible. Restore the pre-migration backup instead.'
        );
    }

    private function backfillGroupsFromLegacyCenters(): void
    {
        $targetByLegacyCenterName = [
            'مركز السلام القرآني - مجموعة س' => ['مركز السلام القرآني', 'مجموعة س'],
            'مركز السلام القرآني' => ['مركز السلام القرآني', 'مجموعة س'],
            'مركز السلام القرآني - المجموعة الأولى' => ['مركز السلام القرآني', 'المجموعة الأولى'],
            'مركز السلام القرآني - المجموعة الثانية' => ['مركز السلام القرآني', 'المجموعة الثانية'],
            'مركز السلام القرآني | المجموعة الأولى | إقراء' => ['مركز السلام القرآني', 'المجموعة الأولى إقراء'],
            'مركز السلام القرآني | المجموعة الثانية | إقراء' => ['مركز السلام القرآني', 'المجموعة الثانية إقراء'],
            'مشروع الحافظ المتميز - النساء' => ['مشروع الحافظ المتميز - النساء', 'مجموعة النساء'],
            'الحفاظ' => ['مركز السلام القرآني', 'الحفاظ'],
            'مشروع الحافظ المتميز \\ البناء والإرتقاء' => ['مشروع الحافظ المتميز', 'مجموعة البناء والارتقاء'],
            'مشروع الحافظ المتميز' => ['مشروع الحافظ المتميز', 'مجموعة البناء والارتقاء'],
            'مشروع الحافظ المتميز \\ الأشبال' => ['مشروع الحافظ المتميز', 'مجموعة الأشبال'],
            'مشروع الحافظ المتميز \\ التميز' => ['مشروع الحافظ المتميز', 'مجموعة التميز'],
            'دار القرآن مسجد "الصالحين"' => ['دار القرآن مسجد "الصالحين"', 'مجموعة دار قرآن "الصالحين"'],
        ];

        foreach (DB::table('centers')->orderBy('id')->get(['id', 'name']) as $center) {
            $target = $targetByLegacyCenterName[$center->name] ?? null;
            $groupId = $target !== null
                ? DB::table('groups')
                    ->join('centers', 'groups.center_id', '=', 'centers.id')
                    ->where('centers.name', $target[0])
                    ->where('groups.name', $target[1])
                    ->value('groups.id')
                : null;

            if ($groupId === null) {
                $groupIds = DB::table('groups')->where('center_id', $center->id)->pluck('id');
                if ($groupIds->count() === 1) {
                    $groupId = $groupIds->first();
                }
            }

            if ($groupId === null) {
                continue;
            }

            DB::table('homeworks')
                ->where('center_id', $center->id)
                ->whereNull('group_id')
                ->update(['group_id' => $groupId]);
        }
    }

    private function assertBackfillIsCompleteAndUnique(): void
    {
        $unmappedIds = DB::table('homeworks')
            ->whereNull('group_id')
            ->orderBy('id')
            ->limit(20)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($unmappedIds !== []) {
            throw new RuntimeException(
                'Cannot migrate homeworks to groups. Unmapped homework IDs: '.implode(', ', $unmappedIds),
            );
        }

        $duplicate = DB::table('homeworks')
            ->select(['group_id', 'date'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('group_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('group_id')
            ->orderBy('date')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(sprintf(
                'Cannot enforce one homework per group and date. Duplicate found for group %s on %s.',
                (string) $duplicate->group_id,
                (string) $duplicate->date,
            ));
        }
    }
};
