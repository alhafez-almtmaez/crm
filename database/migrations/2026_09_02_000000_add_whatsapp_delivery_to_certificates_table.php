<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->string('whatsapp_delivery_status', 24)
                ->nullable()
                ->index()
                ->after('revoked_reason');
            $table->dateTime('whatsapp_sent_at')
                ->nullable()
                ->index()
                ->after('whatsapp_delivery_status');
            $table->foreignId('whatsapp_sent_by')
                ->nullable()
                ->after('whatsapp_sent_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('whatsapp_image_filename')->nullable()->after('whatsapp_sent_by');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropForeign(['whatsapp_sent_by']);
            $table->dropIndex(['whatsapp_sent_at']);
            $table->dropIndex(['whatsapp_delivery_status']);
            $table->dropColumn([
                'whatsapp_delivery_status',
                'whatsapp_sent_at',
                'whatsapp_sent_by',
                'whatsapp_image_filename',
            ]);
        });
    }
};
