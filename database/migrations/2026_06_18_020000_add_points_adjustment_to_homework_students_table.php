<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_students', static function (Blueprint $table): void {
            if (! Schema::hasColumn('homework_students', 'points_adjustment')) {
                $table->integer('points_adjustment')->default(0)->after('points_balance_before');
            }
        });
    }

    public function down(): void
    {
        Schema::table('homework_students', static function (Blueprint $table): void {
            if (Schema::hasColumn('homework_students', 'points_adjustment')) {
                $table->dropColumn('points_adjustment');
            }
        });
    }
};
