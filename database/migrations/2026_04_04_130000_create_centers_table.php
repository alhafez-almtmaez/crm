<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centers', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone', 20);
            $table->string('group_serialized')->nullable();
            $table->json('working_days');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
