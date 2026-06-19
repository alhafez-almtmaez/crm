<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_student_points', static function (Blueprint $table): void {
            if (! Schema::hasColumn('homework_student_points', 'is_next_homework')) {
                $table->boolean('is_next_homework')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('homework_student_points', static function (Blueprint $table): void {
            if (Schema::hasColumn('homework_student_points', 'is_next_homework')) {
                $table->dropColumn('is_next_homework');
            }
        });
    }
};
