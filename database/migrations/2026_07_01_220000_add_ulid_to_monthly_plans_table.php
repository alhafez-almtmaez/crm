<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_plans', static function (Blueprint $table): void {
            if (! Schema::hasColumn('monthly_plans', 'ulid')) {
                $table->string('ulid', 26)->nullable()->after('id')->unique();
            }
        });

        DB::table('monthly_plans')
            ->whereNull('ulid')
            ->orderBy('id')
            ->eachById(static function ($monthlyPlan): void {
                DB::table('monthly_plans')
                    ->where('id', $monthlyPlan->id)
                    ->update(['ulid' => (string) Str::ulid()]);
            });
    }

    public function down(): void
    {
        Schema::table('monthly_plans', static function (Blueprint $table): void {
            if (Schema::hasColumn('monthly_plans', 'ulid')) {
                $table->dropColumn('ulid');
            }
        });
    }
};
