<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            $table->string('certificate_portal_delivery_status', 24)
                ->nullable()
                ->after('certificate_portal_id')
                ->index();
            $table->string('certificate_portal_delivery_fingerprint', 64)
                ->nullable()
                ->after('certificate_portal_delivery_status');
            $table->dateTime('certificate_portal_delivery_claimed_at')
                ->nullable()
                ->after('certificate_portal_delivery_fingerprint');
            $table->dateTime('certificate_portal_sent_at')
                ->nullable()
                ->after('certificate_portal_delivery_claimed_at')
                ->index();
            $table->foreignId('certificate_portal_sent_by')
                ->nullable()
                ->after('certificate_portal_sent_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            $table->dropForeign(['certificate_portal_sent_by']);
            $table->dropIndex(['certificate_portal_delivery_status']);
            $table->dropIndex(['certificate_portal_sent_at']);
            $table->dropColumn([
                'certificate_portal_delivery_status',
                'certificate_portal_delivery_fingerprint',
                'certificate_portal_delivery_claimed_at',
                'certificate_portal_sent_at',
                'certificate_portal_sent_by',
            ]);
        });
    }
};
