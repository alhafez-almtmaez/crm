<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_types', static function (Blueprint $table): void {
            $table->string('category', 16)->default('quran')->after('name')->index();
        });

        DB::table('plan_types')
            ->where('name', 'حفظ السنة')
            ->update(['category' => 'sunnah']);
    }

    public function down(): void
    {
        Schema::table('plan_types', static function (Blueprint $table): void {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
