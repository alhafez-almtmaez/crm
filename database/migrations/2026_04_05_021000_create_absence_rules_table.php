<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained('centers')->cascadeOnDelete();
            $table->string('attendance_type', 40)->index(); // absence | excused_absence
            $table->unsignedSmallInteger('occurrence_number');
            $table->string('action', 40)->index(); // freeze_student | dismiss_student
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->boolean('send_to_center_group')->default(false);
            $table->string('freeze_reason', 255)->nullable();
            $table->unsignedTinyInteger('freeze_working_days_count')->default(4);
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['center_id', 'attendance_type', 'occurrence_number'], 'absence_rules_unique_match');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_rules');
    }
};
