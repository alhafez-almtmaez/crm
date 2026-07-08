<?php

use App\Services\Admin\WhatsAppPendingMessageService;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
})->purpose('Send stored pending WhatsApp messages when a device is connected');
