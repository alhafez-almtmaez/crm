<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_plans', static function (Blueprint $table): void {
            if (! Schema::hasColumn('monthly_plans', 'holiday_dates')) {
                $table->json('holiday_dates')->nullable()->after('end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_plans', static function (Blueprint $table): void {
            if (Schema::hasColumn('monthly_plans', 'holiday_dates')) {
                $table->dropColumn('holiday_dates');
            }
        });
    }
};
