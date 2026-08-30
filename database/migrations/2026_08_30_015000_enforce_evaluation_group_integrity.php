<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
                'Cannot enforce group-scoped evaluations. Unmapped evaluation IDs: '.implode(', ', $unmappedIds),
            );
        }

        $duplicate = DB::table('evaluations')
            ->select(['group_id', 'date'])
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('group_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('group_id')
            ->orderBy('date')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(sprintf(
                'Cannot enforce one evaluation per group and date. Duplicate found for group %s on %s.',
                (string) $duplicate->group_id,
                (string) $duplicate->date,
            ));
        }

        Schema::table('evaluations', static function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
        });

        Schema::table('evaluations', static function (Blueprint $table): void {
            $table->unsignedBigInteger('group_id')->nullable(false)->change();
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', static function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
        });

        Schema::table('evaluations', static function (Blueprint $table): void {
            $table->unsignedBigInteger('group_id')->nullable()->change();
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->nullOnDelete();
        });
    }
};
