<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_rule_execution_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations')->nullOnDelete();
            $table->foreignId('evaluation_student_id')->nullable()->constrained('evaluations_users')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->foreignId('absence_rule_id')->nullable()->constrained('absence_rules')->nullOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->string('attendance_type', 40)->index();
            $table->unsignedTinyInteger('attendance_value')->nullable();
            $table->unsignedSmallInteger('occurrence_number')->nullable();
            $table->string('action', 40)->index();
            $table->json('recipient_phones')->nullable();
            $table->text('message_template_snapshot')->nullable();
            $table->text('message_content')->nullable();
            $table->boolean('sent_to_group')->default(false);
            $table->boolean('was_message_sent')->default(false);
            $table->boolean('student_was_frozen')->default(false);
            $table->foreignId('student_freeze_id')->nullable()->constrained('student_freezes')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['evaluation_id', 'student_id']);
            $table->index(['center_id', 'attendance_type', 'occurrence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_rule_execution_logs');
    }
};
