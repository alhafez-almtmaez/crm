<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', static function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'points_balance')) {
                $table->integer('points_balance')->default(0)->after('deducted_points_count');
            }
        });

        Schema::create('homeworks', static function (Blueprint $table): void {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['center_id', 'date']);
        });

        Schema::create('homework_students', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plan_types')->nullOnDelete();
            $table->foreignId('current_plan_point_id')->nullable()->constrained('plan_points')->nullOnDelete();
            $table->integer('points_balance_before')->default(0);
            $table->integer('points_balance_after')->default(0);
            $table->timestamps();

            $table->unique(['homework_id', 'student_id']);
            $table->index(['student_id', 'plan_id']);
        });

        Schema::create('homework_student_points', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homework_student_id')->constrained('homework_students')->cascadeOnDelete();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('plan_point_id')->nullable()->constrained('plan_points')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_done')->default(false)->index();
            $table->unsignedInteger('awarded_points')->default(0);
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_student_id', 'plan_point_id']);
            $table->index(['student_id', 'plan_point_id']);
        });

        Schema::create('student_point_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('homework_id')->nullable()->constrained('homeworks')->nullOnDelete();
            $table->foreignId('homework_student_point_id')->nullable()->constrained('homework_student_points')->nullOnDelete();
            $table->foreignId('plan_point_id')->nullable()->constrained('plan_points')->nullOnDelete();
            $table->string('type', 50)->default('homework_completed')->index();
            $table->integer('points')->default(0);
            $table->integer('balance_before')->default(0);
            $table->integer('balance_after')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'plan_point_id', 'type'], 'student_point_transactions_once_unique');
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_point_transactions');
        Schema::dropIfExists('homework_student_points');
        Schema::dropIfExists('homework_students');
        Schema::dropIfExists('homeworks');

        Schema::table('students', static function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'points_balance')) {
                $table->dropColumn('points_balance');
            }
        });
    }
};
