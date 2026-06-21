<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_points', static function (Blueprint $table): void {
            if (! Schema::hasColumn('plan_points', 'weight')) {
                $table->decimal('weight', 5, 2)->default(1)->after('points');
            }

            if (! Schema::hasColumn('plan_points', 'is_standalone')) {
                $table->boolean('is_standalone')->default(false)->after('weight');
            }
        });

        Schema::table('students', static function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'max_daily_weight')) {
                $table->decimal('max_daily_weight', 5, 2)->default(2)->after('current_plan_point_id');
            }

            if (! Schema::hasColumn('students', 'monthly_plan_cursor_point_id')) {
                $table->foreignId('monthly_plan_cursor_point_id')
                    ->nullable()
                    ->after('max_daily_weight')
                    ->constrained('plan_points')
                    ->nullOnDelete();
            }
        });

        Schema::create('student_monthly_plans', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plan_types')->nullOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('max_daily_weight', 5, 2)->default(2);
            $table->foreignId('starts_after_plan_point_id')->nullable()->constrained('plan_points')->nullOnDelete();
            $table->foreignId('ends_at_plan_point_id')->nullable()->constrained('plan_points')->nullOnDelete();
            $table->unsignedInteger('generated_items_count')->default(0);
            $table->unsignedInteger('skipped_items_count')->default(0);
            $table->string('status', 30)->default('generated')->index();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'year', 'month'], 'student_monthly_plans_student_month_unique');
            $table->index(['center_id', 'group_id', 'year', 'month']);
        });

        Schema::create('student_monthly_plan_days', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_monthly_plan_id')
                ->constrained('student_monthly_plans')
                ->cascadeOnDelete();
            $table->date('date')->index();
            $table->unsignedTinyInteger('day_number');
            $table->decimal('total_weight', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['student_monthly_plan_id', 'date'], 'student_monthly_plan_days_plan_date_unique');
        });

        Schema::create('student_monthly_plan_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_monthly_plan_id')
                ->constrained('student_monthly_plans')
                ->cascadeOnDelete();
            $table->foreignId('student_monthly_plan_day_id')
                ->nullable()
                ->constrained('student_monthly_plan_days')
                ->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('plan_point_id')->nullable()->constrained('plan_points')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->decimal('weight', 5, 2)->default(1);
            $table->boolean('is_standalone')->default(false)->index();
            $table->string('status', 30)->default('generated')->index();
            $table->timestamps();

            $table->unique(['student_monthly_plan_id', 'plan_point_id'], 'student_monthly_plan_items_plan_point_unique');
            $table->index(['student_id', 'plan_point_id']);
        });

        $this->syncPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('student_monthly_plan_items');
        Schema::dropIfExists('student_monthly_plan_days');
        Schema::dropIfExists('student_monthly_plans');

        Schema::table('students', static function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'monthly_plan_cursor_point_id')) {
                $table->dropConstrainedForeignId('monthly_plan_cursor_point_id');
            }

            if (Schema::hasColumn('students', 'max_daily_weight')) {
                $table->dropColumn('max_daily_weight');
            }
        });

        Schema::table('plan_points', static function (Blueprint $table): void {
            foreach (['weight', 'is_standalone'] as $column) {
                if (Schema::hasColumn('plan_points', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function syncPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            'monthly_plans.view',
            'monthly_plans.create',
            'monthly_plans.update',
            'monthly_plans.delete',
        ];

        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')
                ->where('name', $permission)
                ->where('guard_name', 'web')
                ->exists();

            if ($exists) {
                DB::table('permissions')
                    ->where('name', $permission)
                    ->where('guard_name', 'web')
                    ->update(['updated_at' => $now]);

                continue;
            }

            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminRoleId = DB::table('roles')
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($adminRoleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }
    }
};
