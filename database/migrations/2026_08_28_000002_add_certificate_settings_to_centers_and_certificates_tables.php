<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', static function (Blueprint $table): void {
            $table->string('certificate_name')->nullable()->after('name');
            $table->boolean('show_center_manager_signature')->default(true)->after('working_days');
        });

        Schema::table('certificates', static function (Blueprint $table): void {
            $table->boolean('show_center_manager_signature')->default(true)->after('center_name');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropColumn('show_center_manager_signature');
        });

        Schema::table('centers', static function (Blueprint $table): void {
            $table->dropColumn(['certificate_name', 'show_center_manager_signature']);
        });
    }
};
