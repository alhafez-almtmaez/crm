<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'current_plan_point_id')) {
                $table->foreignId('current_plan_point_id')
                    ->nullable()
                    ->after('plan_type_id')
                    ->constrained('plan_points')
                    ->nullOnDelete();
            }
        });

        DB::table('students')
            ->whereNull('current_plan_point_id')
            ->whereNotNull('plan_type_id')
            ->select(['id', 'plan_type_id'])
            ->orderBy('id')
            ->eachById(static function ($student): void {
                $planPointId = DB::table('plan_points')
                    ->join('student_point_transactions', 'plan_points.id', '=', 'student_point_transactions.plan_point_id')
                    ->where('student_point_transactions.student_id', $student->id)
                    ->where('student_point_transactions.type', 'homework_completed')
                    ->where('plan_points.plan_id', $student->plan_type_id)
                    ->orderByDesc('plan_points.sort_order')
                    ->orderByDesc('plan_points.id')
                    ->value('plan_points.id');

                if ($planPointId !== null) {
                    DB::table('students')
                        ->where('id', $student->id)
                        ->update(['current_plan_point_id' => $planPointId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'current_plan_point_id')) {
                $table->dropConstrainedForeignId('current_plan_point_id');
            }
        });
    }
};
