<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Admin\BulkCertificateDeliveryService;
use App\Services\Admin\StudentCertificatePortalWhatsAppService;
use App\Services\System\SystemSettingsService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class BulkDeliverStudentCertificatePortals extends Command
{
    private const LOCK_NAME = 'certificates:bulk-portal-deliver';

    private const LOCK_SECONDS = 604_800;

    protected $signature = 'certificates:bulk-portal-deliver
        {--before=2026-09-01 : Exclusive achievement cutoff in the configured business timezone}
        {--stage=all : all, issue, or send}
        {--student-id= : Limit issuance and delivery to one student}
        {--execute : Issue and/or send; without this option the command is read-only}
        {--actor= : User ID recorded as certificate issuer and portal sender}
        {--min-delay=30 : Minimum seconds between WhatsApp messages}
        {--max-delay=60 : Maximum seconds between WhatsApp messages}
        {--yes : Skip the execution confirmation}';

    protected $description = 'Issue historical certificates and send each active student one portal link to the student and guardian';

    public function handle(
        BulkCertificateDeliveryService $certificates,
        StudentCertificatePortalWhatsAppService $portals,
        SystemSettingsService $settings,
    ): int {
        $options = $this->validatedOptions($settings);
        if ($options === null) {
            return SymfonyCommand::FAILURE;
        }

        $missing = $certificates->missingIssueCandidates(
            $options['cutoff_utc'],
            activeOnly: true,
            studentId: $options['student_id'],
        );
        $portalPreview = $portals->preview(
            $options['cutoff_utc'],
            activeOnly: true,
            studentId: $options['student_id'],
            additionalStudentIds: in_array($options['stage'], ['all', 'issue'], true)
                ? $missing->pluck('student_id')->all()
                : [],
        );
        $this->renderPreview($missing->count(), $portalPreview, $options);

        if (! $options['execute']) {
            $this->newLine();
            $this->comment('DRY RUN: no certificates were issued and no WhatsApp portal links were sent.');
            $this->line('Add --execute --actor=USER_ID to perform the operation.');

            return SymfonyCommand::SUCCESS;
        }

        $actor = $this->executionActor($options['actor_id'], $options['stage']);
        if (! $actor instanceof User) {
            return SymfonyCommand::FAILURE;
        }

        if (! $options['yes'] && ! $this->confirm(
            'This will issue certificates and/or send student portal links through WhatsApp. Continue?',
            false,
        )) {
            $this->warn('Cancelled. No changes were made.');

            return SymfonyCommand::SUCCESS;
        }

        $lock = Cache::lock(self::LOCK_NAME, self::LOCK_SECONDS);
        if (! $lock->get()) {
            $this->error('Another bulk certificate portal command is already running.');

            return SymfonyCommand::FAILURE;
        }

        return $this->runDelivery($certificates, $portals, $actor, $options, $lock);
    }

    /** @return array<string, mixed>|null */
    private function validatedOptions(SystemSettingsService $settings): ?array
    {
        $stage = strtolower(trim((string) $this->option('stage')));
        if (! in_array($stage, ['all', 'issue', 'send'], true)) {
            $this->error('The --stage option must be one of: all, issue, send.');

            return null;
        }

        $studentId = $this->positiveOptionalInteger($this->option('student-id'));
        if ($this->option('student-id') !== null
            && $this->option('student-id') !== ''
            && $studentId === null) {
            $this->error('The --student-id option must be a positive integer.');

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
        if ((bool) $this->option('execute') && app()->environment('production') && $minimumDelay < 30) {
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
            'student_id' => $studentId,
            'execute' => (bool) $this->option('execute'),
            'actor_id' => $actorId,
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
        if (! $actor instanceof User) {
            $this->error('The selected actor user does not exist.');

            return null;
        }

        $permissions = match ($stage) {
            'issue' => ['students.update'],
            'send' => ['certificates.send'],
            default => ['students.update', 'certificates.send'],
        };
        $missing = collect($permissions)
            ->reject(static fn (string $permission): bool => $actor->can($permission))
            ->values();
        if ($missing->isNotEmpty()) {
            $this->error('The selected actor is missing permissions: '.$missing->implode(', '));

            return null;
        }

        return $actor;
    }

    /**
     * @param  array<string, int>  $portalPreview
     * @param  array<string, mixed>  $options
     */
    private function renderPreview(int $missingCertificates, array $portalPreview, array $options): void
    {
        $stage = (string) $options['stage'];
        $pendingMessages = $stage === 'issue' ? 0 : (int) $portalPreview['pending_messages'];

        $this->info('Bulk student certificate portal preview');
        $this->line("Cutoff: before {$options['before']} ({$options['timezone']})");
        $this->line('Cutoff UTC: '.$options['cutoff_utc']->format('Y-m-d H:i:s'));
        $this->line("Stage: {$stage}");
        if ($options['student_id'] !== null) {
            $this->line('Student filter: '.$options['student_id']);
        }
        $this->line("Missing certificates to issue: {$missingCertificates}");
        $this->line('Students with a portal to deliver: '.$portalPreview['students']);
        $this->line('Students pending a portal message: '.$portalPreview['pending_students']);
        $this->line("Projected configured WhatsApp messages: {$pendingMessages}");
        $this->line(sprintf(
            'Existing portal states — sent: %d, unregistered: %d, review: %d, processing: %d, no recipient: %d.',
            $portalPreview['already_sent'],
            $portalPreview['unregistered'],
            $portalPreview['review_required'],
            $portalPreview['processing'],
            $portalPreview['no_recipient'],
        ));

        if ($pendingMessages > 0) {
            $intervals = max(0, $pendingMessages - 1);
            $minimumSeconds = $intervals * (int) $options['min_delay'];
            $maximumSeconds = $intervals * (int) $options['max_delay'];
            $this->line(sprintf(
                'Pure pacing estimate: %s minimum / %s maximum (API time not included).',
                $this->duration($minimumSeconds),
                $this->duration($maximumSeconds),
            ));
        }
    }

    /** @param array<string, mixed> $options */
    private function runDelivery(
        BulkCertificateDeliveryService $certificates,
        StudentCertificatePortalWhatsAppService $portals,
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
                $issueSummary = $certificates->issueMissing(
                    $options['cutoff_utc'],
                    activeOnly: true,
                    onProgress: fn (array $event) => $this->renderProgress($event),
                    studentId: $options['student_id'],
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
                $this->info('Sending one certificate portal link per student to the student and guardian...');
                $sendSummary = $portals->sendPending(
                    $options['cutoff_utc'],
                    activeOnly: true,
                    studentId: $options['student_id'],
                    onProgress: fn (array $event) => $this->renderProgress($event),
                );
                $this->line(sprintf(
                    'Send summary: checked=%d attempted_students=%d sent_students=%d sent_messages=%d already=%d unregistered=%d review=%d processing=%d inactive=%d no_recipient=%d failed=%d',
                    $sendSummary['checked'],
                    $sendSummary['attempted_students'],
                    $sendSummary['sent_students'],
                    $sendSummary['sent_messages'],
                    $sendSummary['already_sent'],
                    $sendSummary['unregistered'],
                    $sendSummary['review_required'],
                    $sendSummary['processing'],
                    $sendSummary['inactive'],
                    $sendSummary['no_recipient'],
                    $sendSummary['failed'],
                ));
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The bulk portal command stopped because of an unexpected error.');

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

        if (($issueSummary['failed'] ?? 0) > 0
            || ($sendSummary['failed'] ?? 0) > 0
            || ($sendSummary['review_required'] ?? 0) > 0
            || ($sendSummary['processing'] ?? 0) > 0) {
            $this->warn('Completed with failures or delivery states that require review.');

            return SymfonyCommand::FAILURE;
        }

        $this->info('Completed successfully. Unregistered and missing WhatsApp recipients were skipped.');

        return SymfonyCommand::SUCCESS;
    }

    /** @param array<string, int|string|null> $event */
    private function renderProgress(array $event): void
    {
        $student = $event['student_id'] ?? '—';
        $status = $event['status'] ?? 'unknown';
        $index = $event['index'] ?? '?';
        $total = $event['total'] ?? '?';

        $this->line("[{$index}/{$total}] student={$student} status={$status}");
    }

    private function positiveOptionalInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ctype_digit((string) $value) && (int) $value > 0 ? (int) $value : null;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        return ctype_digit((string) $value) ? (int) $value : null;
    }

    private function duration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
