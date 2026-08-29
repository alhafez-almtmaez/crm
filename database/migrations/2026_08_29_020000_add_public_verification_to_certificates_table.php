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
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('ulid');
            $table->string('status', 16)->default('valid')->after('certificate_number');
            $table->dateTime('revoked_at')->nullable()->after('issued_at');
            $table->text('revoked_reason')->nullable()->after('revoked_at');
        });

        DB::table('certificates')
            ->select('id')
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById(500, static function ($certificates): void {
                foreach ($certificates as $certificate) {
                    DB::table('certificates')
                        ->where('id', $certificate->id)
                        ->whereNull('public_id')
                        ->update(['public_id' => (string) Str::uuid()]);
                }
            });

        Schema::table('certificates', static function (Blueprint $table): void {
            $table->uuid('public_id')->nullable(false)->change();
            $table->unique('public_id');
        });

        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
        });

        Schema::table('certificates', static function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->change();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
        });

        $hasOrphanedCertificates = DB::table('certificates')->whereNull('student_id')->exists();

        Schema::table('certificates', static function (Blueprint $table) use ($hasOrphanedCertificates): void {
            if (! $hasOrphanedCertificates) {
                $table->foreignId('student_id')->nullable(false)->change();
            }

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });

        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn([
                'public_id',
                'status',
                'revoked_at',
                'revoked_reason',
            ]);
        });
    }
};
