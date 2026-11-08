<?php

namespace App\Services\Admin;

use App\Models\Homework;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class HomeworkCertificateDeliveryService
{
    private const LOCK_SECONDS = 21_600;

    public function __construct(
        private readonly StudentCertificateService $certificates,
        private readonly CertificateWhatsAppService $certificateWhatsApp,
        private readonly WhatsAppMessagingService $whatsAppMessaging,
        private readonly AdminDataScopeService $dataScope,
        private readonly HomeworkCertificateEligibilityService $eligibility,
    ) {}

    /**
     * Issue certificates first earned through the selected homework, then send
     * each certificate as a PDF attachment. Existing and previously sent
     * certificates are kept idempotent by the underlying certificate services.
     *
     * @return array{
     *     candidates: int,
     *     checked: int,
     *     issued: int,
     *     existing: int,
     *     attempted: int,
     *     sent: int,
     *     already_sent: int,
     *     partial: int,
     *     review_required: int,
     *     failed: int,
     *     has_issues: bool,
     *     failures: array<int, array{student_id: int, plan_point_id: int, stage: string, reason: string}>
     * }
     */
    public function deliver(Homework $homework): array
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        $lock = Cache::lock($this->lockKey($homework), self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'homework' => __('homeworks.certificate_delivery_in_progress'),
            ]);
        }

        try {
            $targets = $this->eligibility->targets($homework);
            $summary = [
                'candidates' => count($targets),
                'checked' => 0,
                'issued' => 0,
                'existing' => 0,
                'attempted' => 0,
                'sent' => 0,
                'already_sent' => 0,
                'partial' => 0,
                'review_required' => 0,
                'failed' => 0,
                'has_issues' => false,
                'failures' => [],
            ];

            foreach ($targets as $index => $target) {
                $summary['checked']++;
                $transaction = $target['transaction'];
                $checkpoint = $target['checkpoint'];

                try {
                    $certificate = $this->certificates->issueFromCompletion(
                        $transaction,
                        (int) $checkpoint->id,
                    );

                    if ($certificate->wasRecentlyCreated) {
                        $summary['issued']++;
                    } else {
                        $summary['existing']++;
                    }
                } catch (Throwable $exception) {
                    $this->recordFailure($summary, $transaction, $checkpoint, 'issue', $exception);
                    Log::error('Homework certificate issuance failed.', [
                        'homework_id' => (int) $homework->id,
                        'transaction_id' => (int) $transaction->id,
                        'student_id' => (int) $transaction->student_id,
                        'plan_point_id' => (int) $checkpoint->id,
                        'exception_class' => $exception::class,
                    ]);

                    continue;
                }

                $student = Student::query()->find($certificate->student_id);
                if (! $student instanceof Student) {
                    $this->recordMissingStudent($summary, $transaction, $checkpoint);

                    continue;
                }

                $sendProducedResult = false;

                try {
                    $summary['attempted']++;
                    $result = $this->certificateWhatsApp->send($student, $certificate);
                    $sendProducedResult = ! $result['already_sent'];

                    if ($result['already_sent']) {
                        $summary['already_sent']++;
                    } elseif ($result['uncertain']) {
                        $summary['review_required']++;
                        $summary['has_issues'] = true;
                    } elseif ($result['partial']) {
                        $summary['partial']++;
                        $summary['has_issues'] = true;
                    } else {
                        $summary['sent']++;
                    }
                } catch (Throwable $exception) {
                    $this->recordFailure($summary, $transaction, $checkpoint, 'send', $exception);
                    Log::warning('Homework certificate WhatsApp delivery failed.', [
                        'homework_id' => (int) $homework->id,
                        'certificate_id' => (int) $certificate->id,
                        'student_id' => (int) $certificate->student_id,
                        'plan_point_id' => (int) $certificate->plan_point_id,
                        'exception_class' => $exception::class,
                    ]);
                }

                if ($sendProducedResult && $index < count($targets) - 1) {
                    $this->whatsAppMessaging->waitBetweenMessages();
                }
            }

            return $summary;
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $summary */
    private function recordFailure(
        array &$summary,
        StudentPointTransaction $transaction,
        PlanPoint $checkpoint,
        string $stage,
        Throwable $exception,
    ): void {
        $summary['failed']++;
        $summary['has_issues'] = true;
        $summary['failures'][] = [
            'student_id' => (int) $transaction->student_id,
            'plan_point_id' => (int) $checkpoint->id,
            'stage' => $stage,
            'reason' => $this->failureReason($exception),
        ];
    }

    /** @param array<string, mixed> $summary */
    private function recordMissingStudent(
        array &$summary,
        StudentPointTransaction $transaction,
        PlanPoint $checkpoint,
    ): void {
        $summary['failed']++;
        $summary['has_issues'] = true;
        $summary['failures'][] = [
            'student_id' => (int) $transaction->student_id,
            'plan_point_id' => (int) $checkpoint->id,
            'stage' => 'send',
            'reason' => (string) __('homeworks.certificate_delivery_student_missing'),
        ];
    }

    private function failureReason(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())
                ->flatten()
                ->first(static fn (mixed $item): bool => is_string($item) && trim($item) !== '');

            if (is_string($message)) {
                return $message;
            }
        }

        return (string) __('homeworks.certificate_delivery_unexpected_error');
    }

    private function lockKey(Homework $homework): string
    {
        return "homework:{$homework->id}:certificate-delivery";
    }
}
