<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absence_rules')) {
            return;
        }

        Schema::table('absence_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('absence_rules', 'freeze_start_after_days')) {
                $table->dropColumn('freeze_start_after_days');
            }

            if (Schema::hasColumn('absence_rules', 'freeze_end_offset_days')) {
                $table->dropColumn('freeze_end_offset_days');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('absence_rules')) {
            return;
        }

        Schema::table('absence_rules', function (Blueprint $table): void {
            if (!Schema::hasColumn('absence_rules', 'freeze_start_after_days')) {
                $table->unsignedTinyInteger('freeze_start_after_days')->default(1)->after('freeze_working_days_count');
            }

            if (!Schema::hasColumn('absence_rules', 'freeze_end_offset_days')) {
                $table->unsignedTinyInteger('freeze_end_offset_days')->default(2)->after('freeze_start_after_days');
            }
        });
    }
};
