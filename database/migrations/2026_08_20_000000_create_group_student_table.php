<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_student', static function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['group_id', 'student_id']);
            $table->index(['student_id', 'group_id']);
        });

        $now = now();

        DB::table('students')
            ->whereNotNull('group_id')
            ->select(['id', 'group_id'])
            ->orderBy('id')
            ->chunk(500, static function ($students) use ($now): void {
                DB::table('group_student')->insertOrIgnore(
                    $students->map(static fn ($student): array => [
                        'group_id' => (int) $student->group_id,
                        'student_id' => (int) $student->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_student');
    }
};
