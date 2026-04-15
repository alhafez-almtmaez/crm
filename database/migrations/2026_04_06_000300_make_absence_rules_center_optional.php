<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absence_rules')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE absence_rules ALTER COLUMN center_id DROP NOT NULL');
        DB::statement('ALTER TABLE absence_rules DROP CONSTRAINT IF EXISTS absence_rules_unique_match');
        DB::statement('DROP INDEX IF EXISTS absence_rules_center_unique_match');
        DB::statement('DROP INDEX IF EXISTS absence_rules_global_unique_match');

        DB::statement(
            'CREATE UNIQUE INDEX absence_rules_center_unique_match
            ON absence_rules (center_id, attendance_type, occurrence_number)
            WHERE center_id IS NOT NULL',
        );

        DB::statement(
            'CREATE UNIQUE INDEX absence_rules_global_unique_match
            ON absence_rules (attendance_type, occurrence_number)
            WHERE center_id IS NULL',
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('absence_rules')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS absence_rules_center_unique_match');
        DB::statement('DROP INDEX IF EXISTS absence_rules_global_unique_match');
        DB::statement(
            'ALTER TABLE absence_rules
            ADD CONSTRAINT absence_rules_unique_match UNIQUE (center_id, attendance_type, occurrence_number)',
        );

        $hasNullCenters = DB::table('absence_rules')->whereNull('center_id')->exists();
        if (!$hasNullCenters) {
            DB::statement('ALTER TABLE absence_rules ALTER COLUMN center_id SET NOT NULL');
        }
    }
};
