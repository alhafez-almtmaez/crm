<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_plans', static function (Blueprint $table): void {
            if (! Schema::hasColumn('monthly_plans', 'start_date')) {
                $table->date('start_date')->nullable()->after('year');
            }

            if (! Schema::hasColumn('monthly_plans', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        DB::table('monthly_plans')
            ->where(static function ($query): void {
                $query->whereNull('start_date')
                    ->orWhereNull('end_date');
            })
            ->chunkById(100, static function ($plans): void {
                foreach ($plans as $plan) {
                    $startDate = CarbonImmutable::create((int) $plan->year, (int) $plan->month, 1)->startOfDay();

                    DB::table('monthly_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'start_date' => $startDate->toDateString(),
                            'end_date' => $startDate->endOfMonth()->toDateString(),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('monthly_plans', static function (Blueprint $table): void {
            foreach (['end_date', 'start_date'] as $column) {
                if (Schema::hasColumn('monthly_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
