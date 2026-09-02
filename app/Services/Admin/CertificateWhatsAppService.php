<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\Certificate;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CertificateWhatsAppService
{
    private const LOCK_SECONDS = 300;

    public function __construct(
        private readonly CertificatePdfRenderer $pdfRenderer,
        private readonly WhatsAppMessagingService $messagingService,
    ) {}

    /**
     * @return array{certificate: Certificate, message: string, partial: bool, already_sent: bool, uncertain: bool}
     */
    public function send(Student $student, Certificate $certificate): array
    {
        $lock = Cache::lock($this->lockKey($certificate), self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'certificate' => __('certificates.whatsapp_send_in_progress'),
            ]);
        }

        try {
            /** @var Certificate $freshCertificate */
            $freshCertificate = Certificate::query()
                ->whereKey($certificate->id)
                ->where('student_id', $student->id)
                ->firstOrFail();

            if ($freshCertificate->whatsapp_sent_at !== null) {
                return [
                    'certificate' => $freshCertificate,
                    'message' => __('certificates.whatsapp_already_sent'),
                    'partial' => false,
                    'already_sent' => true,
                    'uncertain' => false,
                ];
            }

            if ($freshCertificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED) {
                return [
                    'certificate' => $freshCertificate,
                    'message' => __('certificates.whatsapp_delivery_review_required'),
                    'partial' => false,
                    'already_sent' => false,
                    'uncertain' => true,
                ];
            }

            if ($freshCertificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_PROCESSING) {
                if ($this->isStaleProcessingClaim($freshCertificate)) {
                    $uncertainCertificate = $this->markDeliveryAsUncertain(
                        $student,
                        $freshCertificate,
                        $freshCertificate->whatsapp_image_filename
                            ?: $this->pdfFilename($freshCertificate),
                    );

                    return [
                        'certificate' => $uncertainCertificate,
                        'message' => __('certificates.whatsapp_delivery_review_required'),
                        'partial' => false,
                        'already_sent' => false,
                        'uncertain' => true,
                    ];
                }

                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_send_in_progress'),
                ]);
            }

            if ($freshCertificate->status !== Certificate::STATUS_VALID) {
                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_invalid_certificate'),
                ]);
            }

            $phones = $this->recipientPhones($student);
            if ($phones === []) {
                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_no_phone'),
                ]);
            }

            $filename = $this->pdfFilename($freshCertificate);
            $freshCertificate = $this->claimDelivery($student, $freshCertificate, $filename);

            try {
                $this->messagingService->assertHasEligibleRecipients($phones);
            } catch (WhatsAppMessageSendException $exception) {
                $this->releaseDeliveryClaim($student, $freshCertificate);

                throw ValidationException::withMessages([
                    'certificate' => $exception->getMessage(),
                ]);
            } catch (Throwable $exception) {
                $this->releaseDeliveryClaim($student, $freshCertificate);

                Log::error('Certificate WhatsApp recipient verification failed.', [
                    'certificate_id' => (int) $freshCertificate->id,
                    'exception' => $exception,
                ]);

                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_send_failed'),
                ]);
            }

            try {
                $pdfBytes = $this->pdfRenderer->render($student, $freshCertificate);
            } catch (Throwable $exception) {
                $this->releaseDeliveryClaim($student, $freshCertificate);

                Log::error('Certificate PDF rendering failed before WhatsApp delivery.', [
                    'certificate_id' => (int) $freshCertificate->id,
                    'exception' => $exception,
                ]);

                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_pdf_failed'),
                ]);
            }

            $caption = __('certificates.whatsapp_caption', [
                'student' => (string) $freshCertificate->student_name,
                'type' => $this->achievementTypeLabel((string) $freshCertificate->achievement_type),
                'achievement' => (string) $freshCertificate->achievement_name,
                'number' => (string) $freshCertificate->certificate_number,
                'url' => $this->verificationUrl($freshCertificate),
            ]);

            try {
                $this->messagingService->sendPdfDocument($phones, $caption, $pdfBytes, $filename);
            } catch (WhatsAppMessageSendException $exception) {
                $delivery = $this->messagingService->deliveryFailureMeta($phones, null, $exception);

                if ($delivery['delivered_chat_ids'] === []) {
                    if (! $exception->deliveryAttempted()) {
                        $this->releaseDeliveryClaim($student, $freshCertificate);

                        throw ValidationException::withMessages([
                            'certificate' => $exception->getMessage(),
                        ]);
                    }

                    $uncertainCertificate = $this->markDeliveryAsUncertain(
                        $student,
                        $freshCertificate,
                        $filename,
                    );

                    Log::warning('Certificate WhatsApp delivery result is unknown after the send request started.', [
                        'certificate_id' => (int) $uncertainCertificate->id,
                        'exception' => $exception,
                    ]);

                    return [
                        'certificate' => $uncertainCertificate,
                        'message' => __('certificates.whatsapp_delivery_review_required'),
                        'partial' => false,
                        'already_sent' => false,
                        'uncertain' => true,
                    ];
                }

                $sentCertificate = $this->markAsSent(
                    $student,
                    $freshCertificate,
                    $filename,
                    Certificate::WHATSAPP_DELIVERY_PARTIAL,
                );

                Log::warning('Certificate WhatsApp delivery completed only for part of the recipient batch.', [
                    'certificate_id' => (int) $sentCertificate->id,
                    'delivered_recipient_count' => count($delivery['delivered_chat_ids']),
                    'remaining_recipient_count' => count($delivery['remaining_chat_ids']),
                ]);

                return [
                    'certificate' => $sentCertificate,
                    'message' => __('certificates.whatsapp_sent_partially'),
                    'partial' => true,
                    'already_sent' => false,
                    'uncertain' => false,
                ];
            } catch (InvalidArgumentException $exception) {
                $this->releaseDeliveryClaim($student, $freshCertificate);

                Log::error('Certificate PDF was rejected before WhatsApp delivery.', [
                    'certificate_id' => (int) $freshCertificate->id,
                    'exception' => $exception,
                ]);

                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_pdf_failed'),
                ]);
            } catch (Throwable $exception) {
                $uncertainCertificate = $this->markDeliveryAsUncertain(
                    $student,
                    $freshCertificate,
                    $filename,
                );

                Log::warning('Certificate WhatsApp delivery result is unknown.', [
                    'certificate_id' => (int) $uncertainCertificate->id,
                    'exception' => $exception,
                ]);

                return [
                    'certificate' => $uncertainCertificate,
                    'message' => __('certificates.whatsapp_delivery_review_required'),
                    'partial' => false,
                    'already_sent' => false,
                    'uncertain' => true,
                ];
            }

            return [
                'certificate' => $this->markAsSent(
                    $student,
                    $freshCertificate,
                    $filename,
                    Certificate::WHATSAPP_DELIVERY_SENT,
                ),
                'message' => __('certificates.whatsapp_sent_successfully'),
                'partial' => false,
                'already_sent' => false,
                'uncertain' => false,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<int, string>
     */
    public function recipientPhones(Student $student): array
    {
        return collect([$student->parent_phone_number, $student->phone_number])
            ->filter(static fn (mixed $phone): bool => is_string($phone) && trim($phone) !== '')
            ->map(static fn (string $phone): string => trim($phone))
            ->unique()
            ->values()
            ->all();
    }

    private function markAsSent(
        Student $student,
        Certificate $certificate,
        string $filename,
        string $deliveryStatus,
    ): Certificate {
        return DB::transaction(function () use ($student, $certificate, $filename, $deliveryStatus): Certificate {
            /** @var Certificate $lockedCertificate */
            $lockedCertificate = Certificate::query()
                ->whereKey($certificate->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCertificate->whatsapp_sent_at === null) {
                $lockedCertificate->forceFill([
                    'whatsapp_delivery_status' => $deliveryStatus,
                    'whatsapp_sent_at' => now(),
                    'whatsapp_sent_by' => Auth::id(),
                    'whatsapp_image_filename' => $filename,
                ])->save();
            }

            return $lockedCertificate->refresh();
        });
    }

    private function claimDelivery(
        Student $student,
        Certificate $certificate,
        string $filename,
    ): Certificate {
        return DB::transaction(function () use ($student, $certificate, $filename): Certificate {
            /** @var Certificate $lockedCertificate */
            $lockedCertificate = Certificate::query()
                ->whereKey($certificate->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCertificate->whatsapp_sent_at !== null) {
                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_already_sent'),
                ]);
            }

            if ($lockedCertificate->whatsapp_delivery_status !== null) {
                throw ValidationException::withMessages([
                    'certificate' => $lockedCertificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED
                        ? __('certificates.whatsapp_delivery_review_required')
                        : __('certificates.whatsapp_send_in_progress'),
                ]);
            }

            if ($lockedCertificate->status !== Certificate::STATUS_VALID) {
                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_invalid_certificate'),
                ]);
            }

            $lockedCertificate->forceFill([
                'whatsapp_delivery_status' => Certificate::WHATSAPP_DELIVERY_PROCESSING,
                'whatsapp_image_filename' => $filename,
            ])->save();

            return $lockedCertificate->refresh();
        });
    }

    private function releaseDeliveryClaim(Student $student, Certificate $certificate): void
    {
        Certificate::query()
            ->whereKey($certificate->id)
            ->where('student_id', $student->id)
            ->where('whatsapp_delivery_status', Certificate::WHATSAPP_DELIVERY_PROCESSING)
            ->update([
                'whatsapp_delivery_status' => null,
                'whatsapp_image_filename' => null,
            ]);
    }

    private function markDeliveryAsUncertain(
        Student $student,
        Certificate $certificate,
        string $filename,
    ): Certificate {
        return DB::transaction(function () use ($student, $certificate, $filename): Certificate {
            /** @var Certificate $lockedCertificate */
            $lockedCertificate = Certificate::query()
                ->whereKey($certificate->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCertificate->forceFill([
                'whatsapp_delivery_status' => Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED,
                'whatsapp_image_filename' => $filename,
            ])->save();

            return $lockedCertificate->refresh();
        });
    }

    private function pdfFilename(Certificate $certificate): string
    {
        $number = preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $certificate->certificate_number);
        $number = trim((string) $number, '-');

        $achievement = $this->achievementTypeLabel((string) $certificate->achievement_type)
            .'-'.(string) $certificate->achievement_name;
        $achievement = preg_replace('/[^\p{L}\p{M}\p{N}_-]+/u', '-', $achievement) ?? '';
        $achievement = trim(preg_replace('/-+/u', '-', $achievement) ?? '', '-');
        $achievement = mb_strcut($achievement, 0, 120, 'UTF-8');

        if ($achievement === '') {
            $achievement = Str::slug(__('certificates.types.achievement')) ?: 'achievement';
        }

        return 'شهادة-'.$achievement.'-'.($number !== '' ? $number : (string) $certificate->ulid).'.pdf';
    }

    private function achievementTypeLabel(string $type): string
    {
        return match ($type) {
            Certificate::ACHIEVEMENT_SURAH => __('certificates.types.surah'),
            Certificate::ACHIEVEMENT_PART => __('certificates.types.part'),
            Certificate::ACHIEVEMENT_THREE_PARTS => __('certificates.types.three_parts'),
            Certificate::ACHIEVEMENT_SUNNAH_BOOK => __('certificates.types.sunnah_book'),
            Certificate::ACHIEVEMENT_SUNNAH_PART => __('certificates.types.sunnah_part'),
            default => __('certificates.types.achievement'),
        };
    }

    private function verificationUrl(Certificate $certificate): string
    {
        $path = route('certificates.verify', ['public_id' => $certificate->public_id], absolute: false);

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private function lockKey(Certificate $certificate): string
    {
        return "certificate:{$certificate->id}:whatsapp-send";
    }

    private function isStaleProcessingClaim(Certificate $certificate): bool
    {
        return $certificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_PROCESSING
            && $certificate->updated_at !== null
            && $certificate->updated_at->lte(
                now()->subMinutes(Certificate::WHATSAPP_PROCESSING_STALE_AFTER_MINUTES),
            );
    }
}
