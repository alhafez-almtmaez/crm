<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name', 100);
            $table->string('second_name', 100);
            $table->string('middle_name', 100);
            $table->string('last_name', 100);
            $table->string('full_name', 255)->index();
            $table->string('id_number', 30)->nullable();
            $table->string('parent_phone_number', 20)->nullable();
            $table->string('phone_number', 20)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('plan_type_id')->nullable()->constrained('plan_types')->nullOnDelete();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
