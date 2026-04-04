<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('center_id')
                ->constrained('centers')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['name', 'center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
