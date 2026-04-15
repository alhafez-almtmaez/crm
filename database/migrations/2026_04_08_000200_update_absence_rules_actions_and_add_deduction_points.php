<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absence_rules')) {
            return;
        }

        Schema::table('absence_rules', function (Blueprint $table): void {
            if (!Schema::hasColumn('absence_rules', 'deduction_points_count')) {
                $table->unsignedSmallInteger('deduction_points_count')->default(0)->after('freeze_working_days_count');
            }
        });

        DB::table('absence_rules')
            ->whereIn('action', ['send_message', 'send_message_and_freeze'])
            ->update(['action' => 'freeze_student']);

        DB::table('absence_rules')
            ->whereNotIn('action', ['freeze_student', 'dismiss_student'])
            ->update(['action' => 'freeze_student']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('absence_rules')) {
            return;
        }

        DB::table('absence_rules')
            ->where('action', 'dismiss_student')
            ->update(['action' => 'freeze_student']);

        Schema::table('absence_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('absence_rules', 'deduction_points_count')) {
                $table->dropColumn('deduction_points_count');
            }
        });
    }
};
