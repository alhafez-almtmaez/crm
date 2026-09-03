<?php

namespace App\Services\System;

use App\Models\Certificate;
use App\Models\Student;
use Illuminate\Support\Str;

class StudentCertificatePortalService
{
    public function __construct(
        private readonly CertificateAchievementService $achievements,
        private readonly SystemSettingsService $settings,
    ) {}

    public function findStudent(string $portalId): ?Student
    {
        if (! Str::isUuid($portalId, version: 4)) {
            return null;
        }

        return Student::query()
            ->with('center:id,name')
            ->where('certificate_portal_id', $portalId)
            ->first();
    }

    public function slug(Student|string $student): string
    {
        $name = $student instanceof Student ? (string) $student->full_name : $student;
        $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', trim($name)) ?? '';
        $slug = trim(preg_replace('/-+/u', '-', $slug) ?? '', '-');
        $slug = trim(mb_strcut($slug, 0, 160, 'UTF-8'), '-');

        return $slug !== '' ? $slug : 'student';
    }

    public function url(Student $student): string
    {
        return route('certificate-portals.show', [
            'portal_id' => (string) $student->certificate_portal_id,
        ]);
    }

    public function previewUrl(Student $student, Certificate $certificate): string
    {
        return route('certificate-portals.certificates.show', [
            'portal_id' => (string) $student->certificate_portal_id,
            'certificate_public_id' => (string) $certificate->public_id,
        ]);
    }

    public function pdfUrl(Student $student, Certificate $certificate): string
    {
        return route('certificate-portals.certificates.pdf', [
            'portal_id' => (string) $student->certificate_portal_id,
            'certificate_public_id' => (string) $certificate->public_id,
        ]);
    }

    public function findValidCertificate(Student $student, string $publicId): ?Certificate
    {
        if (! Str::isUuid($publicId, version: 4)) {
            return null;
        }

        return Certificate::query()
            ->where('student_id', $student->getKey())
            ->where('public_id', $publicId)
            ->where('status', Certificate::STATUS_VALID)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Student $student): array
    {
        $student->loadMissing('center:id,name');
        $certificates = $student->certificates()
            ->select([
                'certificates.id',
                'certificates.student_id',
                'certificates.public_id',
                'certificates.plan_name',
                'certificates.plan_point_name',
                'certificates.achievement_type',
                'certificates.achievement_name',
                'certificates.gregorian_date',
                'certificates.achieved_at',
            ])
            ->where('status', Certificate::STATUS_VALID)
            ->oldest('achieved_at')
            ->oldest('id')
            ->get();
        $settings = $this->settings->get();
        $logoUrl = collect([
            $settings['logoLightUrl'] ?? null,
            $settings['logoUrl'] ?? null,
            $settings['logoDarkUrl'] ?? null,
        ])->first(static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return [
            'student_name' => (string) $student->full_name,
            'center_name' => $student->center?->name,
            'certificate_count' => $certificates->count(),
            'portal_url' => $this->url($student),
            'brand_name' => trim((string) ($settings['brandName'] ?? config('app.name'))),
            'brand_tagline' => trim((string) ($settings['brandTagline'] ?? '')),
            'logo_url' => is_string($logoUrl) ? $logoUrl : asset('media/logos/logo.png'),
            'certificates' => $certificates
                ->values()
                ->map(fn (Certificate $certificate, int $index): array => $this->item(
                    $student,
                    $certificate,
                    $index + 1,
                ))
                ->all(),
        ];
    }

    public function pdfFilename(Student $student, Certificate $certificate): string
    {
        $parts = [
            'شهادة',
            $this->typeLabel((string) $certificate->achievement_type),
            $this->achievementName($certificate),
            (string) $student->full_name,
        ];
        $filename = implode('-', array_filter($parts, static fn (string $part): bool => trim($part) !== ''));
        $filename = preg_replace('/[^\p{L}\p{M}\p{N}_-]+/u', '-', $filename) ?? '';
        $filename = trim(preg_replace('/-+/u', '-', $filename) ?? '', '-');
        $filename = trim(mb_strcut($filename, 0, 200, 'UTF-8'), '-');

        return ($filename !== '' ? $filename : 'certificate').'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Student $student, Certificate $certificate, int $position): array
    {
        return [
            'position' => $position,
            'type' => (string) $certificate->achievement_type,
            'type_label' => $this->typeLabel((string) $certificate->achievement_type),
            'category' => $this->category((string) $certificate->achievement_type),
            'achievement_name' => $this->achievementName($certificate),
            'plan_name' => $this->nullableTrim($certificate->plan_name),
            'plan_point_name' => $this->nullableTrim($certificate->plan_point_name),
            'gregorian_date' => trim((string) $certificate->gregorian_date),
            'preview_url' => $this->previewUrl($student, $certificate),
            'pdf_url' => $this->pdfUrl($student, $certificate),
        ];
    }

    private function achievementName(Certificate $certificate): string
    {
        return $this->achievements->displayName(
            (string) $certificate->achievement_type,
            (string) $certificate->achievement_name,
        );
    }

    private function typeLabel(string $type): string
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

    private function category(string $type): string
    {
        return in_array($type, [
            Certificate::ACHIEVEMENT_SUNNAH_BOOK,
            Certificate::ACHIEVEMENT_SUNNAH_PART,
        ], true) ? 'sunnah' : 'quran';
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
