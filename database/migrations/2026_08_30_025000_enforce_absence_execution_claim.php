<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('absence_rule_execution_logs')
            ->whereNotNull('evaluation_student_id')
            ->select('evaluation_student_id')
            ->groupBy('evaluation_student_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                "Cannot enforce absence execution claims: evaluation item {$duplicate->evaluation_student_id} has duplicate logs."
            );
        }

        Schema::table('absence_rule_execution_logs', function (Blueprint $table): void {
            $table->unique('evaluation_student_id');
        });
    }

    public function down(): void
    {
        Schema::table('absence_rule_execution_logs', function (Blueprint $table): void {
            $table->dropUnique(['evaluation_student_id']);
        });
    }
};
