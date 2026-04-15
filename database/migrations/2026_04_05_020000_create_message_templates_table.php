<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120);
            $table->string('name', 150);
            $table->string('locale', 8)->default('ar');
            $table->text('content');
            $table->json('placeholders')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
