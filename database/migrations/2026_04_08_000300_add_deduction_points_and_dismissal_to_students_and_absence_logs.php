<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table): void {
                if (!Schema::hasColumn('students', 'deducted_points_count')) {
                    $table->unsignedInteger('deducted_points_count')->default(0)->after('is_active');
                }
            });
        }

        if (Schema::hasTable('absence_rule_execution_logs')) {
            Schema::table('absence_rule_execution_logs', function (Blueprint $table): void {
                if (!Schema::hasColumn('absence_rule_execution_logs', 'student_was_dismissed')) {
                    $table->boolean('student_was_dismissed')->default(false)->after('student_was_frozen');
                }

                if (!Schema::hasColumn('absence_rule_execution_logs', 'deduction_points_count')) {
                    $table->unsignedSmallInteger('deduction_points_count')->default(0)->after('student_freeze_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('absence_rule_execution_logs')) {
            Schema::table('absence_rule_execution_logs', function (Blueprint $table): void {
                if (Schema::hasColumn('absence_rule_execution_logs', 'deduction_points_count')) {
                    $table->dropColumn('deduction_points_count');
                }

                if (Schema::hasColumn('absence_rule_execution_logs', 'student_was_dismissed')) {
                    $table->dropColumn('student_was_dismissed');
                }
            });
        }

        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table): void {
                if (Schema::hasColumn('students', 'deducted_points_count')) {
                    $table->dropColumn('deducted_points_count');
                }
            });
        }
    }
};
