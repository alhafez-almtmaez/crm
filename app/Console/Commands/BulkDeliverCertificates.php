<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\User;
use App\Services\Admin\BulkCertificateDeliveryService;
use App\Services\System\SystemSettingsService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class BulkDeliverCertificates extends Command
{
    private const LOCK_NAME = 'certificates:bulk-deliver';

    /** Seven days covers the worst-case 30-60 second pacing window. */
    private const LOCK_SECONDS = 604_800;

    protected $signature = 'certificates:bulk-deliver
        {--before=2026-09-01 : Exclusive cutoff date in the configured business timezone}
        {--stage=all : all, issue, or send}
        {--execute : Issue and/or send; without this option the command is read-only}
        {--actor= : User ID recorded as certificate issuer and WhatsApp sender}
        {--limit= : Maximum records per phase}
        {--min-delay=30 : Minimum seconds between WhatsApp messages}
        {--max-delay=60 : Maximum seconds between WhatsApp messages}
        {--recover-lock : Force-release a stale bulk lock and exit; only after confirming no process is running}
        {--yes : Skip the execution confirmation}';

    protected $description = 'Preview, issue, and send historical achievement certificates to active students';

    public function handle(
        BulkCertificateDeliveryService $deliveries,
        SystemSettingsService $settings,
    ): int {
        if ((bool) $this->option('recover-lock')) {
            return $this->recoverLock();
        }

        $options = $this->validatedOptions($settings);
        if ($options === null) {
            return SymfonyCommand::FAILURE;
        }

        $preview = $deliveries->preview(
            $options['cutoff_utc'],
            activeOnly: true,
            limit: $options['limit'],
        );
        $this->renderPreview($preview, $options);

        if (! $options['execute']) {
            $this->newLine();
            $this->comment('DRY RUN: no certificates were issued and no WhatsApp messages were sent.');
            $this->line('Add --execute --actor=USER_ID to perform the operation.');

            return SymfonyCommand::SUCCESS;
        }

        $actor = $this->executionActor($options['actor_id'], $options['stage']);
        if ($actor === null) {
            return SymfonyCommand::FAILURE;
        }

        if (! $options['yes'] && ! $this->confirm(
            'This will write certificates and/or send WhatsApp PDF files. Continue?',
            false,
        )) {
            $this->warn('Cancelled. No changes were made.');

            return SymfonyCommand::SUCCESS;
        }

        $lock = Cache::lock(self::LOCK_NAME, self::LOCK_SECONDS);
        if (! $lock->get()) {
            $this->error('Another bulk certificate command is already running.');

            return SymfonyCommand::FAILURE;
        }

        return $this->runBulkDelivery($deliveries, $actor, $options, $lock);
    }

    /**
     * @return array{
     *     before: string,
     *     timezone: string,
     *     cutoff_utc: Carbon,
     *     stage: string,
     *     execute: bool,
     *     actor_id: int|null,
     *     limit: int|null,
     *     min_delay: int,
     *     max_delay: int,
     *     yes: bool
     * }|null
     */
    private function validatedOptions(SystemSettingsService $settings): ?array
    {
        $stage = strtolower(trim((string) $this->option('stage')));
        if (! in_array($stage, ['all', 'issue', 'send'], true)) {
            $this->error('The --stage option must be one of: all, issue, send.');

            return null;
        }

        $limit = $this->positiveOptionalInteger($this->option('limit'));
        if ($this->option('limit') !== null && $this->option('limit') !== '' && $limit === null) {
            $this->error('The --limit option must be a positive integer.');

            return null;
        }

        $actorId = $this->positiveOptionalInteger($this->option('actor'));
        if ($this->option('actor') !== null && $this->option('actor') !== '' && $actorId === null) {
            $this->error('The --actor option must be a positive user ID.');

            return null;
        }

        $minimumDelay = $this->nonNegativeInteger($this->option('min-delay'));
        $maximumDelay = $this->nonNegativeInteger($this->option('max-delay'));
        if ($minimumDelay === null || $maximumDelay === null || $maximumDelay < $minimumDelay) {
            $this->error('The delay options must be non-negative integers and max-delay must be at least min-delay.');

            return null;
        }

        if ($maximumDelay > 300) {
            $this->error('The maximum delay cannot exceed 300 seconds.');

            return null;
        }

        $execute = (bool) $this->option('execute');
        if ($execute && app()->environment('production') && $minimumDelay < 30) {
            $this->error('Production execution requires a minimum WhatsApp delay of at least 30 seconds.');

            return null;
        }

        $timezone = (string) ($settings->get()['timezone'] ?? 'Asia/Amman');
        $before = trim((string) $this->option('before'));

        try {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
                throw new \InvalidArgumentException;
            }

            $localCutoff = Carbon::createFromFormat('!Y-m-d', $before, $timezone);
            if ($localCutoff === false || $localCutoff->format('Y-m-d') !== $before) {
                throw new \InvalidArgumentException;
            }
        } catch (Throwable) {
            $this->error('The --before option must be a valid date in YYYY-MM-DD format.');

            return null;
        }

        return [
            'before' => $before,
            'timezone' => $timezone,
            'cutoff_utc' => $localCutoff->copy()->utc(),
            'stage' => $stage,
            'execute' => $execute,
            'actor_id' => $actorId,
            'limit' => $limit,
            'min_delay' => $minimumDelay,
            'max_delay' => $maximumDelay,
            'yes' => (bool) $this->option('yes'),
        ];
    }

    private function executionActor(?int $actorId, string $stage): ?User
    {
        if ($actorId === null) {
            $this->error('The --actor=USER_ID option is required with --execute.');

            return null;
        }

        $actor = User::query()->find($actorId);
        if ($actor === null) {
            $this->error('The selected actor user does not exist.');

            return null;
        }

        $permissions = match ($stage) {
            'issue' => ['students.update'],
            'send' => ['certificates.send'],
            default => ['students.update', 'certificates.send'],
        };
        $missingPermissions = collect($permissions)
            ->reject(static fn (string $permission): bool => $actor->can($permission))
            ->values();

        if ($missingPermissions->isNotEmpty()) {
            $this->error('The selected actor is missing permissions: '.$missingPermissions->implode(', '));

            return null;
        }

        return $actor;
    }

    private function recoverLock(): int
    {
        if (! (bool) $this->option('yes') && ! $this->confirm(
            'Confirm that no certificates:bulk-deliver process is running, then release its stale lock?',
            false,
        )) {
            $this->warn('Lock recovery cancelled.');

            return SymfonyCommand::SUCCESS;
        }

        Cache::lock(self::LOCK_NAME)->forceRelease();
        $this->info('The bulk certificate lock was force-released.');

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $options
     */
    private function renderPreview(array $preview, array $options): void
    {
        $missing = $preview['missing_certificates'];
        $ready = $preview['ready_to_send'];
        $stage = (string) $options['stage'];
        $selected = match ($stage) {
            'issue' => $missing,
            'send' => $ready,
            default => $preview['projected_delivery'],
        };
        $projectedMessages = $stage === 'issue' ? 0 : (int) $selected['configured_recipients'];
        $plans = match ($stage) {
            'issue' => $missing['plans'],
            'send' => $ready['plans'],
            default => $this->mergePlanRows($missing['plans'], $ready['plans']),
        };

        $this->info('Bulk certificate delivery preview');
        $this->line("Cutoff: before {$options['before']} ({$options['timezone']})");
        $this->line('Cutoff UTC: '.$options['cutoff_utc']->format('Y-m-d H:i:s'));
        $this->line('Active students in the system: '.Student::query()
            ->where('is_active', Student::STATUS_ACTIVE)
            ->count());
        $this->line('Source: cumulative homework progress inside each historical plan; reaching a later point includes earlier certificate checkpoints.');
        $this->line('Already-issued valid certificates use their saved achievement date for delivery eligibility.');
        $this->line("Stage: {$stage}");
        $this->line('Missing certificates to issue: '.$missing['certificates']);
        $this->line('Already-issued certificates ready to send: '.$ready['certificates']);
        $this->line('Students in the selected cohort: '.$selected['students']);
        $this->line("Projected configured WhatsApp messages: {$projectedMessages}");

        $this->table(
            ['Plan ID', 'Plan', 'Certificates', 'Students', 'Configured messages'],
            collect($plans)->map(static fn (array $plan): array => [
                $plan['plan_id'],
                $plan['plan_name'],
                $plan['certificates'],
                $plan['students'],
                $plan['configured_recipients'],
            ])->all(),
        );

        if ($projectedMessages > 0 && $stage !== 'issue') {
            $intervals = max(0, $projectedMessages - 1);
            $minimumSeconds = $intervals * (int) $options['min_delay'];
            $maximumSeconds = $intervals * (int) $options['max_delay'];
            $averageSeconds = (int) round($intervals * (((int) $options['min_delay'] + (int) $options['max_delay']) / 2));

            $this->line(sprintf(
                'Pure pacing estimate: %s minimum / %s average / %s maximum (PDF and API time not included).',
                $this->duration($minimumSeconds),
                $this->duration($averageSeconds),
                $this->duration($maximumSeconds),
            ));
        }

        $states = $preview['issued_delivery_states'];
        $this->line(sprintf(
            'Existing delivery states — ready: %d, sent: %d, partial: %d, review: %d, processing: %d, invalid: %d, unknown: %d.',
            $states['ready'],
            $states['sent'],
            $states['partial'],
            $states['review_required'],
            $states['processing'],
            $states['invalid'],
            $states['unknown'],
        ));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function runBulkDelivery(
        BulkCertificateDeliveryService $deliveries,
        User $actor,
        array $options,
        Lock $lock,
    ): int {
        $guard = Auth::guard('web');
        $previousUser = $guard->user();
        $previousMinimumDelay = config('services.whatsapp_api.message_delay_seconds');
        $previousMaximumDelay = config('services.whatsapp_api.message_delay_max_seconds');
        $issueSummary = null;
        $sendSummary = null;

        try {
            $guard->setUser($actor);
            config([
                'services.whatsapp_api.message_delay_seconds' => $options['min_delay'],
                'services.whatsapp_api.message_delay_max_seconds' => $options['max_delay'],
            ]);

            if (in_array($options['stage'], ['all', 'issue'], true)) {
                $this->newLine();
                $this->info('Issuing historical certificates...');
                $issueSummary = $deliveries->issueMissing(
                    $options['cutoff_utc'],
                    activeOnly: true,
                    limit: $options['limit'],
                    onProgress: fn (array $event) => $this->renderProgress($event),
                );
                $this->line(sprintf(
                    'Issue summary: checked=%d issued=%d already=%d failed=%d',
                    $issueSummary['checked'],
                    $issueSummary['issued'],
                    $issueSummary['already_issued'],
                    $issueSummary['failed'],
                ));
            }

            if (in_array($options['stage'], ['all', 'send'], true)) {
                $this->newLine();
                $this->info('Sending certificate PDF files through WhatsApp...');
                $sendSummary = $deliveries->sendPending(
                    $options['cutoff_utc'],
                    activeOnly: true,
                    limit: $options['limit'],
                    onProgress: fn (array $event) => $this->renderProgress($event),
                );
                $this->line(sprintf(
                    'Send summary: checked=%d sent=%d partial=%d review=%d inactive=%d unregistered=%d no_recipient=%d validation_failed=%d failed=%d',
                    $sendSummary['checked'],
                    $sendSummary['sent'],
                    $sendSummary['partial'],
                    $sendSummary['review_required'],
                    $sendSummary['inactive'],
                    $sendSummary['unregistered'],
                    $sendSummary['no_recipient'],
                    $sendSummary['validation_failed'],
                    $sendSummary['failed'],
                ));
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The bulk command stopped because of an unexpected error. No automatic retries were made.');

            return SymfonyCommand::FAILURE;
        } finally {
            config([
                'services.whatsapp_api.message_delay_seconds' => $previousMinimumDelay,
                'services.whatsapp_api.message_delay_max_seconds' => $previousMaximumDelay,
            ]);

            if ($previousUser instanceof User) {
                $guard->setUser($previousUser);
            } else {
                $guard->logout();
            }

            $lock->release();
        }

        $hasProblems = (int) ($issueSummary['failed'] ?? 0) > 0
            || (int) ($sendSummary['partial'] ?? 0) > 0
            || (int) ($sendSummary['review_required'] ?? 0) > 0
            || (int) ($sendSummary['validation_failed'] ?? 0) > 0
            || (int) ($sendSummary['failed'] ?? 0) > 0;

        if ($hasProblems) {
            $this->warn('Completed with items that require attention. Safe items were not retried automatically.');

            return SymfonyCommand::FAILURE;
        }

        $hasSkips = (int) ($sendSummary['inactive'] ?? 0) > 0
            || (int) ($sendSummary['unregistered'] ?? 0) > 0
            || (int) ($sendSummary['no_recipient'] ?? 0) > 0;
        if ($hasSkips) {
            $this->warn('Completed successfully. Inactive students and unavailable WhatsApp recipients were skipped.');

            return SymfonyCommand::SUCCESS;
        }

        $this->info('Bulk certificate operation completed successfully.');

        return SymfonyCommand::SUCCESS;
    }

    /** @param array<string, int|string|null> $event */
    private function renderProgress(array $event): void
    {
        $this->line(sprintf(
            '[%s %d/%d] plan=%d point=%d student=%d status=%s',
            $event['phase'],
            $event['index'],
            $event['total'],
            $event['plan_id'],
            $event['plan_point_id'],
            $event['student_id'],
            $event['status'],
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $first
     * @param  array<int, array<string, mixed>>  $second
     * @return array<int, array<string, mixed>>
     */
    private function mergePlanRows(array $first, array $second): array
    {
        $merged = [];

        foreach ([...$first, ...$second] as $row) {
            $planId = (int) $row['plan_id'];
            $merged[$planId] ??= [
                'plan_id' => $planId,
                'plan_name' => (string) $row['plan_name'],
                'certificates' => 0,
                'students' => 0,
                'student_ids' => [],
                'configured_recipients' => 0,
            ];
            $merged[$planId]['certificates'] += (int) $row['certificates'];
            $merged[$planId]['student_ids'] = array_values(array_unique([
                ...$merged[$planId]['student_ids'],
                ...array_map('intval', $row['student_ids'] ?? []),
            ]));
            $merged[$planId]['students'] = count($merged[$planId]['student_ids']);
            $merged[$planId]['configured_recipients'] += (int) $row['configured_recipients'];
        }

        ksort($merged);

        return array_values($merged);
    }

    private function positiveOptionalInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $validated !== false ? (int) $validated : null;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        return $validated !== false ? (int) $validated : null;
    }

    private function duration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
