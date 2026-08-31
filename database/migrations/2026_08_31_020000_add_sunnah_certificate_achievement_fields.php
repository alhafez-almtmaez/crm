<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_points', static function (Blueprint $table): void {
            $table->string('book_name')->nullable()->after('three_parts');
        });

        Schema::table('certificates', static function (Blueprint $table): void {
            $table->string('book_name')->nullable()->after('three_parts');
        });

        $sunnahPlanIds = DB::table('plan_types')
            ->where('category', 'sunnah')
            ->pluck('id')
            ->all();

        if ($sunnahPlanIds !== []) {
            // Books were historically stored in surah_name because Sunnah did
            // not yet have its own certificate achievement fields.
            DB::table('plan_points')
                ->whereIn('plan_id', $sunnahPlanIds)
                ->whereNotNull('surah_name')
                ->update([
                    'book_name' => DB::raw('surah_name'),
                    'surah_name' => null,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plan_points', 'book_name')) {
            $sunnahPlanIds = Schema::hasColumn('plan_types', 'category')
                ? DB::table('plan_types')->where('category', 'sunnah')->pluck('id')->all()
                : [];

            if ($sunnahPlanIds !== []) {
                DB::table('plan_points')
                    ->whereIn('plan_id', $sunnahPlanIds)
                    ->whereNotNull('book_name')
                    ->whereNull('surah_name')
                    ->update(['surah_name' => DB::raw('book_name')]);
            }
        }

        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropColumn('book_name');
        });

        Schema::table('plan_points', static function (Blueprint $table): void {
            $table->dropColumn('book_name');
        });
    }
};
