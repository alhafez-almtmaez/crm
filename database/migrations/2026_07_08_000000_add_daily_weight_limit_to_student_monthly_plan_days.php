<?php

use App\Support\DailyWeightLimits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_monthly_plan_days')) {
            return;
        }

        Schema::table('student_monthly_plan_days', static function (Blueprint $table): void {
            if (! Schema::hasColumn('student_monthly_plan_days', 'daily_weight_limit')) {
                $table->unsignedTinyInteger('daily_weight_limit')->nullable()->after('total_weight');
            }
        });

        $this->fillExistingDailyLimits();
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_monthly_plan_days')) {
            return;
        }

        Schema::table('student_monthly_plan_days', static function (Blueprint $table): void {
            if (Schema::hasColumn('student_monthly_plan_days', 'daily_weight_limit')) {
                $table->dropColumn('daily_weight_limit');
            }
        });
    }

    private function fillExistingDailyLimits(): void
    {
        if (! Schema::hasColumn('student_monthly_plan_days', 'daily_weight_limit')) {
            return;
        }

        DB::table('student_monthly_plan_days')
            ->join('student_monthly_plans', 'student_monthly_plan_days.student_monthly_plan_id', '=', 'student_monthly_plans.id')
            ->select([
                'student_monthly_plan_days.id',
                'student_monthly_plan_days.date',
                'student_monthly_plans.max_daily_weight',
                'student_monthly_plans.daily_weight_limits',
            ])
            ->orderBy('student_monthly_plan_days.id')
            ->chunk(200, static function ($days): void {
                foreach ($days as $day) {
                    $date = CarbonImmutable::parse($day->date);
                    $limits = self::decodeJsonArray($day->daily_weight_limits ?? null);
                    $limit = DailyWeightLimits::limitForDate($limits, $date, $day->max_daily_weight ?? 2);

                    DB::table('student_monthly_plan_days')
                        ->where('id', $day->id)
                        ->update(['daily_weight_limit' => $limit]);
                }
            });
    }

    /**
     * @return array<string, int>|null
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
