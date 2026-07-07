<?php

use App\Support\DailyWeightLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'daily_weight_limits')) {
                $table->json('daily_weight_limits')->nullable()->after('max_daily_weight');
            }
        });

        if (Schema::hasTable('student_monthly_plans')) {
            Schema::table('student_monthly_plans', static function (Blueprint $table): void {
                if (! Schema::hasColumn('student_monthly_plans', 'daily_weight_limits')) {
                    $table->json('daily_weight_limits')->nullable()->after('max_daily_weight');
                }
            });
        }

        $this->fillStudentDailyWeightLimits();
        $this->fillMonthlyPlanDailyWeightLimits();
    }

    public function down(): void
    {
        if (Schema::hasTable('student_monthly_plans')) {
            Schema::table('student_monthly_plans', static function (Blueprint $table): void {
                if (Schema::hasColumn('student_monthly_plans', 'daily_weight_limits')) {
                    $table->dropColumn('daily_weight_limits');
                }
            });
        }

        Schema::table('students', static function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'daily_weight_limits')) {
                $table->dropColumn('daily_weight_limits');
            }
        });
    }

    private function fillStudentDailyWeightLimits(): void
    {
        if (! Schema::hasColumn('students', 'daily_weight_limits')) {
            return;
        }

        DB::table('students')
            ->leftJoin('centers', 'students.center_id', '=', 'centers.id')
            ->select([
                'students.id',
                'students.max_daily_weight',
                'centers.working_days',
            ])
            ->orderBy('students.id')
            ->chunk(200, static function ($students): void {
                foreach ($students as $student) {
                    $workingDays = self::decodeJsonArray($student->working_days ?? null);
                    $limits = DailyWeightLimits::normalize(
                        limits: null,
                        fallback: $student->max_daily_weight ?? 2,
                        workingDays: $workingDays,
                    );

                    DB::table('students')
                        ->where('id', $student->id)
                        ->update(['daily_weight_limits' => json_encode($limits)]);
                }
            });
    }

    private function fillMonthlyPlanDailyWeightLimits(): void
    {
        if (
            ! Schema::hasTable('student_monthly_plans')
            || ! Schema::hasColumn('student_monthly_plans', 'daily_weight_limits')
        ) {
            return;
        }

        DB::table('student_monthly_plans')
            ->leftJoin('centers', 'student_monthly_plans.center_id', '=', 'centers.id')
            ->select([
                'student_monthly_plans.id',
                'student_monthly_plans.max_daily_weight',
                'centers.working_days',
            ])
            ->orderBy('student_monthly_plans.id')
            ->chunk(200, static function ($plans): void {
                foreach ($plans as $plan) {
                    $workingDays = self::decodeJsonArray($plan->working_days ?? null);
                    $limits = DailyWeightLimits::normalize(
                        limits: null,
                        fallback: $plan->max_daily_weight ?? 2,
                        workingDays: $workingDays,
                    );

                    DB::table('student_monthly_plans')
                        ->where('id', $plan->id)
                        ->update(['daily_weight_limits' => json_encode($limits)]);
                }
            });
    }

    /**
     * @return array<int, string>|null
     */
    private static function decodeJsonArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
};
