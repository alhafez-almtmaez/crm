<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'points_balance')) {
                $table->integer('points_balance')->default(0)->change();
            }
        });

        Schema::table('homework_students', static function (Blueprint $table): void {
            if (Schema::hasColumn('homework_students', 'points_balance_before')) {
                $table->integer('points_balance_before')->default(0)->change();
            }

            if (Schema::hasColumn('homework_students', 'points_balance_after')) {
                $table->integer('points_balance_after')->default(0)->change();
            }
        });

        Schema::table('student_point_transactions', static function (Blueprint $table): void {
            if (Schema::hasColumn('student_point_transactions', 'points')) {
                $table->integer('points')->default(0)->change();
            }

            if (Schema::hasColumn('student_point_transactions', 'balance_before')) {
                $table->integer('balance_before')->default(0)->change();
            }

            if (Schema::hasColumn('student_point_transactions', 'balance_after')) {
                $table->integer('balance_after')->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_point_transactions', static function (Blueprint $table): void {
            if (Schema::hasColumn('student_point_transactions', 'points')) {
                $table->unsignedInteger('points')->default(0)->change();
            }

            if (Schema::hasColumn('student_point_transactions', 'balance_before')) {
                $table->unsignedInteger('balance_before')->default(0)->change();
            }

            if (Schema::hasColumn('student_point_transactions', 'balance_after')) {
                $table->unsignedInteger('balance_after')->default(0)->change();
            }
        });

        Schema::table('homework_students', static function (Blueprint $table): void {
            if (Schema::hasColumn('homework_students', 'points_balance_before')) {
                $table->unsignedInteger('points_balance_before')->default(0)->change();
            }

            if (Schema::hasColumn('homework_students', 'points_balance_after')) {
                $table->unsignedInteger('points_balance_after')->default(0)->change();
            }
        });

        Schema::table('students', static function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'points_balance')) {
                $table->unsignedInteger('points_balance')->default(0)->change();
            }
        });
    }
};
