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
        Schema::table('students', static function (Blueprint $table): void {
            $table->uuid('certificate_portal_id')->nullable()->after('id');
        });

        DB::table('students')
            ->select('id')
            ->whereNull('certificate_portal_id')
            ->orderBy('id')
            ->chunkById(500, static function ($students): void {
                foreach ($students as $student) {
                    DB::table('students')
                        ->where('id', $student->id)
                        ->whereNull('certificate_portal_id')
                        ->update(['certificate_portal_id' => (string) Str::uuid()]);
                }
            });

        Schema::table('students', static function (Blueprint $table): void {
            $table->uuid('certificate_portal_id')->nullable(false)->change();
            $table->unique('certificate_portal_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            $table->dropUnique(['certificate_portal_id']);
            $table->dropColumn('certificate_portal_id');
        });
    }
};
