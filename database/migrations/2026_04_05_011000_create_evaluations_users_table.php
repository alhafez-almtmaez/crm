<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations_users', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('alhifz')->nullable();
            $table->smallInteger('warud')->nullable();
            $table->smallInteger('akhlaqi')->nullable();
            $table->smallInteger('tajwid')->nullable();
            $table->string('note')->nullable();
            $table->unsignedTinyInteger('attendances')->default(1)->index();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // Legacy alias used in old exports/imports.
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->timestamps();

            $table->index(['evaluation_id', 'attendances']);
            $table->index(['student_id', 'attendances']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations_users');
    }
};
