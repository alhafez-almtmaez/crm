<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->json('wording_snapshot')->nullable()->after('design_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', static function (Blueprint $table): void {
            $table->dropColumn('wording_snapshot');
        });
    }
};
