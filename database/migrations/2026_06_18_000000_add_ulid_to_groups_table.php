<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', static function (Blueprint $table): void {
            if (! Schema::hasColumn('groups', 'ulid')) {
                $table->string('ulid', 26)->nullable()->after('id')->unique();
            }
        });

        DB::table('groups')
            ->whereNull('ulid')
            ->orderBy('id')
            ->eachById(static function ($group): void {
                DB::table('groups')
                    ->where('id', $group->id)
                    ->update(['ulid' => (string) Str::ulid()]);
            });
    }

    public function down(): void
    {
        Schema::table('groups', static function (Blueprint $table): void {
            if (Schema::hasColumn('groups', 'ulid')) {
                $table->dropColumn('ulid');
            }
        });
    }
};
