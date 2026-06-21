<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_points') && Schema::hasColumn('plan_points', 'plan_weight_rule_id')) {
            Schema::table('plan_points', static function (Blueprint $table): void {
                $table->dropConstrainedForeignId('plan_weight_rule_id');
            });
        }

        Schema::dropIfExists('plan_weight_rules');

        $this->deleteWeightRulePermissions();
    }

    public function down(): void
    {
    }

    private function deleteWeightRulePermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'plan_weight_rules.view',
                'plan_weight_rules.create',
                'plan_weight_rules.update',
                'plan_weight_rules.delete',
            ])
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
