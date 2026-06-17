<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_types', static function (Blueprint $table): void {
            foreach (['points', 'requires_certificate', 'surah_name', 'part_name', 'three_parts'] as $column) {
                if (Schema::hasColumn('plan_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::create('plan_points', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plan_types')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('name');
            $table->unsignedInteger('points')->nullable();
            $table->boolean('requires_certificate')->default(false)->index();
            $table->string('surah_name')->nullable();
            $table->string('part_name')->nullable();
            $table->string('three_parts')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_points');
    }
};
