<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('message_templates')) {
            return;
        }

        DB::statement('ALTER TABLE message_templates DROP CONSTRAINT IF EXISTS message_templates_center_id_foreign');
        DB::statement('ALTER TABLE message_templates DROP CONSTRAINT IF EXISTS absence_message_templates_center_id_foreign');
        DB::statement('DROP INDEX IF EXISTS message_templates_center_id_key_unique');
        DB::statement('DROP INDEX IF EXISTS absence_message_templates_center_id_key_unique');

        if (Schema::hasColumn('message_templates', 'center_id')) {
            Schema::table('message_templates', function (Blueprint $table): void {
                $table->dropColumn('center_id');
            });
        }

        $this->ensureUniqueKeyIndex();
    }

    public function down(): void
    {
        if (!Schema::hasTable('message_templates')) {
            return;
        }

        if (!Schema::hasColumn('message_templates', 'center_id')) {
            Schema::table('message_templates', function (Blueprint $table): void {
                $table->foreignId('center_id')->nullable()->after('id')->constrained('centers')->nullOnDelete();
            });
        }

        DB::statement('ALTER TABLE message_templates DROP CONSTRAINT IF EXISTS message_templates_key_unique');
        DB::statement('DROP INDEX IF EXISTS message_templates_key_unique');

        Schema::table('message_templates', function (Blueprint $table): void {
            $table->unique(['center_id', 'key']);
        });
    }

    private function ensureUniqueKeyIndex(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $exists = DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', 'message_templates')
                ->where('indexname', 'message_templates_key_unique')
                ->exists();

            if (!$exists) {
                DB::statement('CREATE UNIQUE INDEX message_templates_key_unique ON message_templates ("key")');
            }

            return;
        }

        if ($driver === 'mysql') {
            $exists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'message_templates')
                ->where('index_name', 'message_templates_key_unique')
                ->exists();

            if (!$exists) {
                DB::statement('ALTER TABLE message_templates ADD UNIQUE message_templates_key_unique (`key`)');
            }

            return;
        }

        Schema::table('message_templates', function (Blueprint $table): void {
            $table->unique('key');
        });
    }
};
