<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\System\CertificateAchievementService;
use App\Services\System\DateTimeFormatterService;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CertificateVerificationController extends Controller
{
    public function __construct(
        private readonly CertificateAchievementService $achievements,
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly SystemSettingsService $settings,
    ) {}

    public function __invoke(string $public_id): Response
    {
        if (! Str::isUuid($public_id, version: 4)) {
            return $this->notFoundResponse();
        }

        $certificate = Certificate::query()
            ->where('public_id', $public_id)
            ->first();

        if ($certificate === null || ! in_array($certificate->status, Certificate::STATUSES, true)) {
            return $this->notFoundResponse();
        }

        $status = (string) $certificate->status;
        $verification = [
            ...$this->brandPayload(),
            'found' => true,
            'status' => $status,
            'status_class' => $status,
            'headline' => match ($status) {
                Certificate::STATUS_VALID => 'الشهادة صحيحة ومعتمدة',
                Certificate::STATUS_REVOKED => 'هذه الشهادة ملغاة',
                Certificate::STATUS_REPLACED => 'تم استبدال هذه الشهادة',
            },
            'status_label' => match ($status) {
                Certificate::STATUS_VALID => 'سارية ومعتمدة',
                Certificate::STATUS_REVOKED => 'ملغاة',
                Certificate::STATUS_REPLACED => 'مستبدلة',
            },
            'student_name' => (string) $certificate->student_name,
            'certificate_type' => $this->typeLabel((string) $certificate->achievement_type),
            'achievement' => $this->achievementName($certificate),
            'certificate_number' => (string) $certificate->certificate_number,
            'issued_at' => $this->dateTimeFormatter->formatForAdmin($certificate->issued_at),
        ];

        return $this->response($verification);
    }

    private function notFoundResponse(): Response
    {
        return $this->response([
            ...$this->brandPayload(),
            'found' => false,
            'status' => 'not_found',
            'status_class' => 'not-found',
            'headline' => 'تعذر التحقق من الشهادة',
            'status_label' => 'غير موجودة',
        ], 404);
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function response(array $verification, int $status = 200): Response
    {
        return response()
            ->view('certificates.verify', compact('verification'), $status)
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * @return array{brand_name: string, logo_url: string}
     */
    private function brandPayload(): array
    {
        $settings = $this->settings->get();
        $logoUrl = collect([
            $settings['logoLightUrl'] ?? null,
            $settings['logoUrl'] ?? null,
            $settings['logoDarkUrl'] ?? null,
        ])->first(static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return [
            'brand_name' => trim((string) ($settings['brandName'] ?? config('app.name'))),
            'logo_url' => is_string($logoUrl) ? $logoUrl : asset('media/logos/logo.png'),
        ];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            Certificate::ACHIEVEMENT_SURAH => 'سورة',
            Certificate::ACHIEVEMENT_PART => 'جزء',
            Certificate::ACHIEVEMENT_THREE_PARTS => 'ثلاثة أجزاء',
            default => 'إنجاز',
        };
    }

    private function achievementName(Certificate $certificate): string
    {
        $snapshot = match ($certificate->achievement_type) {
            Certificate::ACHIEVEMENT_SURAH => $certificate->surah_name,
            Certificate::ACHIEVEMENT_PART => $certificate->part_name,
            Certificate::ACHIEVEMENT_THREE_PARTS => $certificate->three_parts,
            default => null,
        };
        $name = trim((string) ($snapshot ?: $certificate->achievement_name));

        return $this->achievements->displayName((string) $certificate->achievement_type, $name);
    }
}
