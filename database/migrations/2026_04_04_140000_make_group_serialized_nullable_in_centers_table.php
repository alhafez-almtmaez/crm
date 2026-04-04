<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('centers')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `centers` MODIFY `group_serialized` VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE centers ALTER COLUMN group_serialized DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('centers')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE `centers` SET `group_serialized` = '' WHERE `group_serialized` IS NULL");
            DB::statement('ALTER TABLE `centers` MODIFY `group_serialized` VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE centers SET group_serialized = '' WHERE group_serialized IS NULL");
            DB::statement('ALTER TABLE centers ALTER COLUMN group_serialized SET NOT NULL');
        }
    }
};
