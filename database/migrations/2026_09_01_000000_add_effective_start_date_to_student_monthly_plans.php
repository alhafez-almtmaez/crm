<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('student_monthly_plans')
            || Schema::hasColumn('student_monthly_plans', 'effective_start_date')
        ) {
            return;
        }

        Schema::table('student_monthly_plans', static function (Blueprint $table): void {
            $table->date('effective_start_date')->nullable()->after('year');
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('student_monthly_plans')
            || ! Schema::hasColumn('student_monthly_plans', 'effective_start_date')
        ) {
            return;
        }

        Schema::table('student_monthly_plans', static function (Blueprint $table): void {
            $table->dropColumn('effective_start_date');
        });
    }
};
