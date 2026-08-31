<?php

use App\Models\MonthlyPlan;
use App\Models\Student;
use App\Services\Admin\StudentMonthlyPlanGenerator;
use App\Services\Admin\WhatsAppPendingMessageService;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('permissions:sync {--prune : Remove DB permissions not defined in config/permissions.php}', function () {
    /** @var PermissionSyncService $service */
    $service = app(PermissionSyncService::class);
    $summary = $service->sync((bool) $this->option('prune'));

    $this->info('Permissions synced successfully.');
    $this->line("Guard: {$summary['guard']}");
    $this->line("Permissions: {$summary['permission_count']}");
    $this->line("Roles: {$summary['role_count']}");
    $this->line("Pruned permissions: {$summary['pruned_permissions']}");
})->purpose('Sync Spatie permissions and role mappings from config/permissions.php');

Artisan::command('whatsapp:send-pending {--limit=50 : Maximum pending messages to process}', function () {
    /** @var WhatsAppPendingMessageService $service */
    $service = app(WhatsAppPendingMessageService::class);
    $summary = $service->flushPending((int) $this->option('limit'));

    $this->info('Pending WhatsApp messages processed.');
    $this->line("Checked: {$summary['checked']}");
    $this->line("Sent: {$summary['sent']}");
    $this->line("Failed: {$summary['failed']}");
    $this->line("Stale: {$summary['stale']}");
})->purpose('Send stored pending WhatsApp messages when a device is connected');

Artisan::command('monthly-plans:sync-memberships {--group= : Limit synchronization to one group ID}', function () {
    $groupId = filled($this->option('group')) ? (int) $this->option('group') : null;
    if ($groupId !== null && $groupId <= 0) {
        $this->error('The group option must be a positive integer.');

        return Command::FAILURE;
    }

    /** @var StudentMonthlyPlanGenerator $generator */
    $generator = app(StudentMonthlyPlanGenerator::class);
    $plans = MonthlyPlan::query()
        ->whereNotNull('group_id')
        ->when($groupId !== null, static fn ($query) => $query->where('group_id', $groupId))
        ->get(['id', 'group_id']);

    $generated = 0;
    $membershipsChecked = 0;

    foreach ($plans->pluck('group_id')->unique() as $planGroupId) {
        $students = Student::query()
            ->whereHas('groups', static fn ($query) => $query->where('groups.id', $planGroupId))
            ->where('is_active', Student::STATUS_ACTIVE)
            ->whereNotNull('plan_type_id')
            ->orderBy('students.id')
            ->get();

        foreach ($students as $student) {
            $membershipsChecked++;
            $result = $generator->syncStudentToExistingGroupPlans($student, [(int) $planGroupId]);
            $generated += (int) $result['generated'];
        }
    }

    $this->info('Monthly-plan memberships synchronized.');
    $this->line("Plans checked: {$plans->count()}");
    $this->line("Memberships checked: {$membershipsChecked}");
    $this->line("Student plans generated: {$generated}");

    return Command::SUCCESS;
})->purpose('Repair students added while an existing group monthly plan was active');
