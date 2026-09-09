<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_monthly_plan_transitions')) {
            return;
        }

        Schema::create('student_monthly_plan_transitions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_monthly_plan_id')
                ->constrained('student_monthly_plans')
                ->cascadeOnDelete();
            $table->date('effective_date');
            $table->foreignId('plan_id')
                ->nullable()
                ->constrained('plan_types')
                ->nullOnDelete();
            $table->foreignId('starts_after_plan_point_id')
                ->nullable()
                ->constrained('plan_points')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['student_monthly_plan_id', 'effective_date'],
                'student_monthly_plan_transitions_plan_date_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_monthly_plan_transitions');
    }
};
