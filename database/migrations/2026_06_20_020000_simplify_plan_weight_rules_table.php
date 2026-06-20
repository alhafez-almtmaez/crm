<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_weight_rules')) {
            return;
        }

        DB::table('plan_weight_rules')
            ->where('name', 'صفحة عادية')
            ->whereNull('pattern')
            ->update(['pattern' => 'صفحة|صفحه']);

        DB::table('plan_weight_rules')
            ->whereIn('name', ['مدى صغير', 'مدى كبير'])
            ->whereNull('pattern')
            ->delete();

        Schema::table('plan_weight_rules', static function (Blueprint $table): void {
            foreach (['keyword', 'min_pages', 'max_pages', 'priority', 'classification'] as $column) {
                if (Schema::hasColumn('plan_weight_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        //
    }
};
