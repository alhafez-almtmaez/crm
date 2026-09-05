<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_point_transactions', static function (Blueprint $table): void {
            $table->foreignId('evaluation_id')
                ->nullable()
                ->after('homework_student_point_id')
                ->constrained('evaluations')
                ->nullOnDelete();
            $table->foreignId('evaluation_student_id')
                ->nullable()
                ->after('evaluation_id')
                ->constrained('evaluations_users')
                ->nullOnDelete();
            $table->foreignId('absence_rule_id')
                ->nullable()
                ->after('evaluation_student_id')
                ->constrained('absence_rules')
                ->nullOnDelete();

            $table->unique(
                ['evaluation_student_id', 'type'],
                'student_point_transactions_attendance_rule_once',
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_point_transactions', static function (Blueprint $table): void {
            $table->dropUnique('student_point_transactions_attendance_rule_once');
            $table->dropConstrainedForeignId('absence_rule_id');
            $table->dropConstrainedForeignId('evaluation_student_id');
            $table->dropConstrainedForeignId('evaluation_id');
        });
    }
};
