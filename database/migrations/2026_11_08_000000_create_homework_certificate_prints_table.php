<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_certificate_prints', static function (Blueprint $table): void {
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at');
            $table->primary(['homework_id', 'certificate_id']);
            $table->index('certificate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_certificate_prints');
    }
};
