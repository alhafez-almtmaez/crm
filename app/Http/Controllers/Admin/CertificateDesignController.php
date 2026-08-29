<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateDesignPdfPreviewRequest;
use App\Http\Requests\Admin\CertificateDesignUpdateRequest;
use App\Models\Center;
use App\Models\Certificate;
use App\Services\Admin\StudentCertificateService;
use App\Services\System\CertificateAchievementService;
use App\Services\System\CertificateDesignSettingsService;
use App\Services\System\CertificateQrCodeService;
use App\Services\System\CertificateWordingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class CertificateDesignController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CertificateDesignSettingsService $settings,
        private readonly CertificateWordingService $wordings,
        private readonly CertificateAchievementService $achievements,
        private readonly StudentCertificateService $certificateRenderer,
        private readonly CertificateQrCodeService $certificateQrCodes,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:certificate_designs.view', only: ['index', 'preview', 'previewPdf']),
            new Middleware('can:certificate_designs.update', only: ['update']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Admin/CertificateDesigns', [
            'canUpdate' => (bool) Auth::user()?->can('certificate_designs.update'),
            'catalog' => $this->settings->catalog(),
            'designs' => $this->settings->get(),
            'previewAchievements' => $this->achievements->previewAchievements(),
            'previewCenters' => $this->previewCenters(),
        ]);
    }

    public function preview(): View
    {
        $previewAchievements = $this->achievements->previewAchievements();
        $previewCenters = $this->previewCenters();
        $achievement = $this->firstPreviewAchievement($previewAchievements);
        $center = $this->firstPreviewCenter($previewCenters);
        $gender = (string) ($center['student_gender'] ?? Center::STUDENT_GENDER_MALE);
        $achievementType = (string) ($achievement['achievement_type'] ?? Certificate::ACHIEVEMENT_SURAH);
        $design = $this->settings->resolve(
            $gender,
            $achievementType,
        );
        $wording = $this->wordings->resolve(
            $gender,
            $achievementType,
        );

        return view('certificates.show', [
            'certificate' => $this->previewPayload(
                $design,
                $wording,
                $this->settings->catalog(),
                $achievement,
                $previewAchievements,
                $center,
                $previewCenters,
                previewMode: true,
            ),
        ]);
    }

    public function previewPdf(CertificateDesignPdfPreviewRequest $request): PdfBuilder
    {
        /** @var array{center_id: int, plan_point_id: int, design: array<string, string>} $validated */
        $validated = $request->validated();
        $centerModel = Center::query()->find((int) $validated['center_id']);
        if ($centerModel === null) {
            throw ValidationException::withMessages([
                'center_id' => __('validation.exists', ['attribute' => 'center id']),
            ]);
        }

        $center = $this->previewCenter($centerModel);
        $achievement = $this->achievements->findPreviewAchievement((int) $validated['plan_point_id']);
        if ($achievement === null) {
            throw ValidationException::withMessages([
                'plan_point_id' => __('certificates.missing_achievement_data'),
            ]);
        }

        $design = $this->settings->resolveDraft(
            $center['student_gender'],
            $achievement['achievement_type'],
            $validated['design'],
        );
        $wording = $this->wordings->resolve(
            $center['student_gender'],
            $achievement['achievement_type'],
        );

        return Pdf::view('certificates.show', [
            'certificate' => $this->previewPayload(
                $design,
                $wording,
                $this->settings->catalog(),
                $achievement,
                center: $center,
                pdf: true,
            ),
        ])
            ->name('certificate-design-preview.pdf')
            ->format('a4')
            ->landscape()
            ->margins(0, 0, 0, 0)
            ->download();
    }

    public function update(CertificateDesignUpdateRequest $request): RedirectResponse
    {
        /** @var array<string, mixed> $designs */
        $designs = $request->validated('designs');
        $this->settings->update($designs);

        return back()->with('success', __('certificates.design_settings_updated'));
    }

    /**
     * @param  array<string, int|string>  $design
     * @param  array<string, int|string>  $wording
     * @param  array<string, mixed>  $catalog
     * @param  array{id: int, achievement_type: string, achievement_name: string, plan_name: string, plan_point_name: string}|null  $achievement
     * @param  array<string, list<array{id: int, achievement_type: string, achievement_name: string, plan_name: string, plan_point_name: string}>>  $previewAchievements
     * @param  array{id: int, name: string, center_name: string, student_gender: string, show_center_manager_signature: bool}|null  $center
     * @param  array<string, list<array{id: int, name: string, center_name: string, student_gender: string, show_center_manager_signature: bool}>>  $previewCenters
     * @return array<string, mixed>
     */
    private function previewPayload(
        array $design,
        array $wording,
        array $catalog,
        ?array $achievement = null,
        array $previewAchievements = [],
        ?array $center = null,
        array $previewCenters = [],
        bool $pdf = false,
        bool $previewMode = false,
    ): array {
        $showCenterIdentity = $center !== null
            && (bool) ($center['show_center_manager_signature'] ?? false);
        $renderAssets = $this->certificateRenderer->renderAssetPayload(
            $design,
            $pdf,
            $previewMode || $showCenterIdentity,
        );
        $themes = collect($catalog['themes'] ?? [])->mapWithKeys(function (array $theme): array {
            $value = (string) ($theme['value'] ?? '');

            return $value !== '' ? [$value => [
                'frame_url' => (string) ($theme['frame_url'] ?? ''),
            ]] : [];
        })->all();
        $fonts = collect($catalog['fonts'] ?? [])->mapWithKeys(static function (array $font): array {
            $value = (string) ($font['value'] ?? '');

            return $value !== '' ? [$value => [
                'body_family' => (string) ($font['body_family'] ?? ''),
                'display_family' => (string) ($font['display_family'] ?? ''),
            ]] : [];
        })->all();
        $gender = (string) ($wording['student_gender'] ?? Center::STUDENT_GENDER_MALE);
        $achievementType = (string) ($wording['achievement_type'] ?? Certificate::ACHIEVEMENT_SURAH);
        $studentNames = [
            Center::STUDENT_GENDER_MALE => 'أَحْمَد مُحَمَّد العَبْدُالله',
            Center::STUDENT_GENDER_FEMALE => 'مَرْيَم أَحْمَد العَبْدُالله',
        ];
        $achievementCatalog = collect($previewAchievements)
            ->flatten(1)
            ->mapWithKeys(static fn (array $option): array => [
                (string) $option['id'] => [
                    'achievement_type' => (string) $option['achievement_type'],
                    'achievement_name' => (string) $option['achievement_name'],
                ],
            ])
            ->all();
        $centerCatalog = collect($previewCenters)
            ->flatten(1)
            ->mapWithKeys(static fn (array $option): array => [
                (string) $option['id'] => [
                    'student_gender' => (string) $option['student_gender'],
                    'center_name' => (string) $option['center_name'],
                    'show_center_manager_signature' => (bool) $option['show_center_manager_signature'],
                ],
            ])
            ->all();
        $wordingSamples = [
            Center::STUDENT_GENDER_MALE => $this->wordingSample(
                $this->wordings->resolve(Center::STUDENT_GENDER_MALE, $achievementType),
            ),
            Center::STUDENT_GENDER_FEMALE => $this->wordingSample(
                $this->wordings->resolve(Center::STUDENT_GENDER_FEMALE, $achievementType),
            ),
        ];

        return [
            'page_title' => (string) config('certificates.title'),
            'title' => (string) config('certificates.title'),
            'quote_first' => (string) config('certificates.quote_first'),
            'quote_second' => (string) config('certificates.quote_second'),
            'project_name' => (string) $wording['project_name'],
            'center_name' => (string) ($center['center_name'] ?? '—'),
            'show_center_manager_signature' => $showCenterIdentity,
            'student_name' => $studentNames[$gender] ?? $studentNames[Center::STUDENT_GENDER_MALE],
            'achievement_intro' => (string) $wording['achievement_intro'],
            'achievement_label' => (string) $wording['achievement_label'],
            'achievement_name' => (string) ($achievement['achievement_name'] ?? '—'),
            'achievement_suffix' => (string) $wording['achievement_suffix'],
            'closing_text' => (string) $wording['closing_text'],
            'center_manager_title' => $previewMode || $showCenterIdentity
                ? (string) config('certificates.center_manager_title')
                : '',
            'project_manager_title' => (string) config('certificates.project_manager_title'),
            'date_title' => (string) config('certificates.date_title'),
            'hijri_date' => '١٥ رَبِيع الأَوَّل ١٤٤٨',
            'gregorian_date' => '٢٠٢٦/٠٨/٢٨',
            'certificate_number' => 'HMT-2026-PREVIEW',
            'intro_before_project' => (string) $wording['intro_before_project'],
            'intro_after_center' => (string) $wording['intro_after_center'],
            'labels' => config('certificates.labels', []),
            'design' => $design,
            'stylesheet_url' => $renderAssets['stylesheet_url'],
            'font_preload_urls' => $renderAssets['font_preload_urls'],
            'images' => $renderAssets['images'],
            'pdf_mode' => $pdf,
            'design_preview_mode' => $previewMode,
            'verification_preview' => true,
            'qr_foreground_color' => $this->certificateQrCodes->foregroundHex(
                (string) ($design['accent_color'] ?? ''),
            ),
            'preview_catalog' => [
                'themes' => $themes,
                'fonts' => $fonts,
                'achievements' => $achievementCatalog,
                'centers' => $centerCatalog,
            ],
            'preview_samples' => [
                'student_names' => $studentNames,
                'achievement_labels' => config('certificates.achievement_labels', []),
                'wording' => $wordingSamples,
            ],
            'back_url' => '#',
            'pdf_url' => '#',
        ];
    }

    /**
     * @param  array<string, int|string>  $wording
     * @return array<string, string>
     */
    private function wordingSample(array $wording): array
    {
        return collect([
            'project_name',
            'intro_before_project',
            'intro_after_center',
            'achievement_intro',
            'achievement_suffix',
            'closing_text',
        ])->mapWithKeys(static fn (string $key): array => [
            $key => (string) ($wording[$key] ?? ''),
        ])->all();
    }

    /**
     * @param  array<string, list<array{id: int, achievement_type: string, achievement_name: string, plan_name: string, plan_point_name: string}>>  $previewAchievements
     * @return array{id: int, achievement_type: string, achievement_name: string, plan_name: string, plan_point_name: string}|null
     */
    private function firstPreviewAchievement(array $previewAchievements): ?array
    {
        foreach ([
            Certificate::ACHIEVEMENT_SURAH,
            Certificate::ACHIEVEMENT_PART,
            Certificate::ACHIEVEMENT_THREE_PARTS,
        ] as $achievementType) {
            $achievement = $previewAchievements[$achievementType][0] ?? null;
            if (is_array($achievement)) {
                return $achievement;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<array{id: int, name: string, center_name: string, student_gender: string, show_center_manager_signature: bool}>>
     */
    private function previewCenters(): array
    {
        $grouped = [
            Center::STUDENT_GENDER_MALE => [],
            Center::STUDENT_GENDER_FEMALE => [],
        ];

        Center::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'certificate_name',
                'student_gender',
                'show_center_manager_signature',
            ])
            ->each(function (Center $center) use (&$grouped): void {
                $option = $this->previewCenter($center);
                $grouped[$option['student_gender']][] = $option;
            });

        return $grouped;
    }

    /**
     * @return array{id: int, name: string, center_name: string, student_gender: string, show_center_manager_signature: bool}
     */
    private function previewCenter(Center $center): array
    {
        $name = $this->nullableTrim($center->name)
            ?? (string) __('certificates.default_center_name');

        return [
            'id' => (int) $center->id,
            'name' => $name,
            'center_name' => $this->nullableTrim($center->certificate_name) ?? $name,
            'student_gender' => in_array($center->student_gender, [
                Center::STUDENT_GENDER_MALE,
                Center::STUDENT_GENDER_FEMALE,
            ], true) ? (string) $center->student_gender : Center::STUDENT_GENDER_MALE,
            'show_center_manager_signature' => (bool) $center->show_center_manager_signature,
        ];
    }

    /**
     * @param  array<string, list<array{id: int, name: string, center_name: string, student_gender: string, show_center_manager_signature: bool}>>  $previewCenters
     * @return array{id: int, name: string, center_name: string, student_gender: string, show_center_manager_signature: bool}|null
     */
    private function firstPreviewCenter(array $previewCenters): ?array
    {
        foreach ([Center::STUDENT_GENDER_MALE, Center::STUDENT_GENDER_FEMALE] as $gender) {
            $center = $previewCenters[$gender][0] ?? null;
            if (is_array($center)) {
                return $center;
            }
        }

        return null;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
