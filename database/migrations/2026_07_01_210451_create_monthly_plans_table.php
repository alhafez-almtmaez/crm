<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_plans', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('students_count')->default(0);
            $table->unsignedInteger('generated_items_count')->default(0);
            $table->unsignedInteger('skipped_items_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'year', 'month'], 'monthly_plans_group_month_unique');
            $table->index(['center_id', 'year', 'month']);
        });

        if (! Schema::hasColumn('student_monthly_plans', 'monthly_plan_id')) {
            Schema::table('student_monthly_plans', static function (Blueprint $table): void {
                $table->foreignId('monthly_plan_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('monthly_plans')
                    ->cascadeOnDelete();
            });
        }

        $this->backfillMonthlyPlans();
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_monthly_plans', 'monthly_plan_id')) {
            Schema::table('student_monthly_plans', static function (Blueprint $table): void {
                $table->dropConstrainedForeignId('monthly_plan_id');
            });
        }

        Schema::dropIfExists('monthly_plans');
    }

    private function backfillMonthlyPlans(): void
    {
        if (! Schema::hasTable('student_monthly_plans')) {
            return;
        }

        $groups = DB::table('student_monthly_plans')
            ->select(['center_id', 'group_id', 'month', 'year'])
            ->whereNull('monthly_plan_id')
            ->groupBy('center_id', 'group_id', 'month', 'year')
            ->get();

        foreach ($groups as $group) {
            $summary = $this->studentPlansForGroup($group)
                ->selectRaw('COUNT(*) as students_count')
                ->selectRaw('COALESCE(SUM(generated_items_count), 0) as generated_items_count')
                ->selectRaw('COALESCE(SUM(skipped_items_count), 0) as skipped_items_count')
                ->selectRaw('MAX(generated_at) as generated_at')
                ->first();

            $monthlyPlanId = DB::table('monthly_plans')->insertGetId([
                'center_id' => $group->center_id,
                'group_id' => $group->group_id,
                'month' => (int) $group->month,
                'year' => (int) $group->year,
                'admin_id' => null,
                'students_count' => (int) ($summary->students_count ?? 0),
                'generated_items_count' => (int) ($summary->generated_items_count ?? 0),
                'skipped_items_count' => (int) ($summary->skipped_items_count ?? 0),
                'generated_at' => $summary->generated_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->studentPlansForGroup($group)->update([
                'monthly_plan_id' => $monthlyPlanId,
            ]);
        }
    }

    private function studentPlansForGroup(object $group): Builder
    {
        $query = DB::table('student_monthly_plans')
            ->where('month', (int) $group->month)
            ->where('year', (int) $group->year);

        foreach (['center_id', 'group_id'] as $column) {
            if ($group->{$column} === null) {
                $query->whereNull($column);

                continue;
            }

            $query->where($column, $group->{$column});
        }

        return $query;
    }
};
