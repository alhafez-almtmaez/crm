<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_point_id')->nullable()->constrained('plan_points')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_number')->unique();

            $table->string('student_name');
            $table->string('center_name')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('plan_point_name');
            $table->string('achievement_type', 32);
            $table->string('achievement_name');
            $table->string('surah_name')->nullable();
            $table->string('part_name')->nullable();
            $table->string('three_parts')->nullable();

            $table->string('title');
            $table->text('quote_first');
            $table->text('quote_second');
            $table->string('project_name');
            $table->text('closing_text');
            $table->string('center_manager_title');
            $table->string('project_manager_title');
            $table->string('date_title');
            $table->string('hijri_date')->nullable();
            $table->string('gregorian_date');
            $table->dateTime('achieved_at');
            $table->dateTime('issued_at');
            $table->timestamps();

            $table->unique(['student_id', 'plan_point_id']);
            $table->index(['student_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
