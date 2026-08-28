<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->json('design_snapshot')->nullable()->after('show_center_manager_signature');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropColumn('design_snapshot');
        });
    }
};
