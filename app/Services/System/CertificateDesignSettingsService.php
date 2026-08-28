<?php

namespace App\Services\System;

use App\Models\Center;
use App\Models\Certificate;
use App\Models\SystemSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CertificateDesignSettingsService
{
    public const SETTING_KEY = 'certificate_designs';

    private const CACHE_KEY = 'vita_certificate_design_settings';

    private const SCHEMA_VERSION = 1;

    /** @var list<string> */
    private const GENDERS = [
        Center::STUDENT_GENDER_MALE,
        Center::STUDENT_GENDER_FEMALE,
    ];

    /** @var list<string> */
    private const ACHIEVEMENT_TYPES = [
        Certificate::ACHIEVEMENT_SURAH,
        Certificate::ACHIEVEMENT_PART,
        Certificate::ACHIEVEMENT_THREE_PARTS,
    ];

    /** @var list<string> */
    private const COLOR_KEYS = [
        'heading_color',
        'student_name_color',
        'content_color',
        'accent_color',
    ];

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function defaults(): array
    {
        $designs = [];

        foreach (self::GENDERS as $gender) {
            foreach (self::ACHIEVEMENT_TYPES as $achievementType) {
                $designs[$gender][$achievementType] = $this->defaultCell($gender);
            }
        }

        return $designs;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function get(): array
    {
        /** @var array<string, array<string, array<string, string>>> $cachedDesigns */
        $cachedDesigns = Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (! Schema::hasTable('system_settings')) {
                return $this->defaults();
            }

            try {
                $record = SystemSetting::query()
                    ->where('key', self::SETTING_KEY)
                    ->first();
            } catch (QueryException) {
                return $this->defaults();
            }

            if ($record === null || ! is_array($record->value)) {
                return $this->defaults();
            }

            $storedDesigns = $record->value['designs'] ?? $record->value;

            return $this->normalize($storedDesigns);
        });

        // Re-normalize cached values against the current catalog. This keeps a
        // long-lived cache safe when a theme or font is renamed during deploy.
        return $this->normalize($cachedDesigns);
    }

    /**
     * @param  array<string, mixed>  $designs
     * @return array<string, array<string, array<string, string>>>
     */
    public function update(array $designs): array
    {
        $normalized = $this->normalize($designs);

        SystemSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => [
                'version' => self::SCHEMA_VERSION,
                'designs' => $normalized,
            ]],
        );

        Cache::forget(self::CACHE_KEY);

        return $this->get();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $themes = collect($this->themes())
            ->map(function (array $theme, string $key): array {
                $framePath = $this->framePath((string) ($theme['frame'] ?? ''));

                return [
                    'value' => $key,
                    'label' => (string) ($theme['label'] ?? $key),
                    'frame' => (string) ($theme['frame'] ?? ''),
                    'frame_url' => $this->versionedAssetUrl($framePath),
                    'heading_color' => $this->normalizeHex($theme['heading_color'] ?? null) ?? '#B67A20',
                    'student_name_color' => $this->normalizeHex($theme['student_name_color'] ?? null) ?? '#0F537F',
                    'content_color' => $this->normalizeHex($theme['content_color'] ?? null) ?? '#263A3D',
                    'accent_color' => $this->normalizeHex($theme['accent_color'] ?? null) ?? '#086C7C',
                ];
            })
            ->values()
            ->all();

        $fonts = collect($this->fonts())
            ->map(static fn (array $font, string $key): array => [
                'value' => $key,
                'label' => (string) ($font['label'] ?? $key),
                'body_family' => (string) ($font['body_family'] ?? ''),
                'display_family' => (string) ($font['display_family'] ?? ''),
            ])
            ->values()
            ->all();

        return [
            'genders' => [
                ['value' => Center::STUDENT_GENDER_MALE, 'label' => __('certificates.genders.male')],
                ['value' => Center::STUDENT_GENDER_FEMALE, 'label' => __('certificates.genders.female')],
            ],
            'achievementTypes' => [
                ['value' => Certificate::ACHIEVEMENT_SURAH, 'label' => __('certificates.types.surah')],
                ['value' => Certificate::ACHIEVEMENT_PART, 'label' => __('certificates.types.part')],
                ['value' => Certificate::ACHIEVEMENT_THREE_PARTS, 'label' => __('certificates.types.three_parts')],
            ],
            'themes' => $themes,
            'fonts' => $fonts,
        ];
    }

    /**
     * Resolve a settings cell to an immutable certificate snapshot.
     *
     * @return array<string, int|string>
     */
    public function resolve(?string $gender, string $achievementType): array
    {
        $gender = $this->gender($gender);
        $achievementType = $this->achievementType($achievementType);
        $cell = $this->get()[$gender][$achievementType];

        return $this->resolvedSnapshot($gender, $achievementType, $cell);
    }

    /**
     * Resolve a validated, unsaved settings cell for a real certificate preview.
     *
     * @param  array<string, mixed>  $cell
     * @return array<string, int|string>
     */
    public function resolveDraft(?string $gender, string $achievementType, array $cell): array
    {
        $gender = $this->gender($gender);
        $achievementType = $this->achievementType($achievementType);
        $matrix = $this->defaults();
        $matrix[$gender][$achievementType] = $cell;
        $normalized = $this->normalize($matrix)[$gender][$achievementType];

        return $this->resolvedSnapshot($gender, $achievementType, $normalized);
    }

    /**
     * @param  array<string, string>  $cell
     * @return array<string, int|string>
     */
    private function resolvedSnapshot(string $gender, string $achievementType, array $cell): array
    {
        $theme = $this->themes()[$cell['theme']];
        $font = $this->fonts()[$cell['font']];

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'student_gender' => $gender,
            'achievement_type' => $achievementType,
            'theme' => $cell['theme'],
            'font' => $cell['font'],
            'frame_path' => $this->framePath((string) $theme['frame']),
            'body_font_family' => (string) $font['body_family'],
            'display_font_family' => (string) $font['display_family'],
            'heading_color' => $cell['heading_color'],
            'student_name_color' => $cell['student_name_color'],
            'content_color' => $cell['content_color'],
            'accent_color' => $cell['accent_color'],
        ];
    }

    /**
     * Normalize an issued snapshot and preserve the exact legacy certificate appearance when absent.
     *
     * @return array<string, int|string>
     */
    public function snapshot(mixed $snapshot, string $achievementType): array
    {
        $legacy = $this->legacySnapshot($achievementType);
        if (! is_array($snapshot)) {
            return $legacy;
        }

        $normalized = $legacy;
        $normalized['schema_version'] = is_numeric($snapshot['schema_version'] ?? null)
            ? max(1, (int) $snapshot['schema_version'])
            : self::SCHEMA_VERSION;

        if (in_array($snapshot['student_gender'] ?? null, self::GENDERS, true)) {
            $normalized['student_gender'] = (string) $snapshot['student_gender'];
        }

        if (in_array($snapshot['achievement_type'] ?? null, self::ACHIEVEMENT_TYPES, true)) {
            $normalized['achievement_type'] = (string) $snapshot['achievement_type'];
        }

        foreach (['theme', 'font'] as $key) {
            $value = $snapshot[$key] ?? null;
            if (is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,50}$/', $value) === 1) {
                $normalized[$key] = $value;
            }
        }

        $framePath = $snapshot['frame_path'] ?? null;
        if (is_string($framePath)
            && preg_match('#^images/certificate/[A-Za-z0-9._-]+$#', $framePath) === 1) {
            $normalized['frame_path'] = $framePath;
        }

        foreach (['body_font_family', 'display_font_family'] as $key) {
            $value = $snapshot[$key] ?? null;
            if (is_string($value) && preg_match('/^[A-Za-z0-9 _-]{1,80}$/', $value) === 1) {
                $normalized[$key] = $value;
            }
        }

        foreach (self::COLOR_KEYS as $key) {
            $normalized[$key] = $this->normalizeHex($snapshot[$key] ?? null) ?? $legacy[$key];
        }

        return $normalized;
    }

    /**
     * @return array<string, int|string>
     */
    public function legacySnapshot(string $achievementType): array
    {
        $achievementType = in_array($achievementType, self::ACHIEVEMENT_TYPES, true)
            ? $achievementType
            : Certificate::ACHIEVEMENT_SURAH;

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'student_gender' => Center::STUDENT_GENDER_MALE,
            'achievement_type' => $achievementType,
            'theme' => 'blue',
            'font' => 'classic',
            'frame_path' => (string) config('certificates.assets.frame', 'images/certificate/certificate-frame.svg'),
            'body_font_family' => 'Certificate Naskh',
            'display_font_family' => 'Certificate Amiri',
            'heading_color' => '#E8A84E',
            'student_name_color' => '#006F89',
            'content_color' => '#111111',
            'accent_color' => '#006F89',
        ];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function normalize(mixed $value): array
    {
        $input = is_array($value) ? $value : [];
        $normalized = [];
        $themes = $this->themes();
        $fonts = $this->fonts();

        foreach (self::GENDERS as $gender) {
            foreach (self::ACHIEVEMENT_TYPES as $achievementType) {
                $fallback = $this->defaultCell($gender);
                $cell = $input[$gender][$achievementType] ?? [];
                $cell = is_array($cell) ? $cell : [];
                $themeKey = is_string($cell['theme'] ?? null) && isset($themes[$cell['theme']])
                    ? $cell['theme']
                    : $fallback['theme'];
                $fontKey = is_string($cell['font'] ?? null) && isset($fonts[$cell['font']])
                    ? $cell['font']
                    : $fallback['font'];
                $theme = $themes[$themeKey];

                $normalized[$gender][$achievementType] = [
                    'theme' => $themeKey,
                    'font' => $fontKey,
                ];

                foreach (self::COLOR_KEYS as $colorKey) {
                    $normalized[$gender][$achievementType][$colorKey] = $this->normalizeHex($cell[$colorKey] ?? null)
                        ?? $this->normalizeHex($theme[$colorKey] ?? null)
                        ?? $fallback[$colorKey];
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function defaultCell(string $gender): array
    {
        $themes = $this->themes();
        $fonts = $this->fonts();
        $configuredTheme = (string) config("certificates.default_themes.{$gender}", '');
        $themeKey = isset($themes[$configuredTheme])
            ? $configuredTheme
            : (string) config('certificates.default_theme', 'blue');

        if (! isset($themes[$themeKey])) {
            $themeKey = (string) array_key_first($themes);
        }

        $fontKey = (string) config('certificates.default_font', 'classic');
        if (! isset($fonts[$fontKey])) {
            $fontKey = (string) array_key_first($fonts);
        }

        $theme = $themes[$themeKey];

        return [
            'theme' => $themeKey,
            'font' => $fontKey,
            'heading_color' => $this->normalizeHex($theme['heading_color'] ?? null) ?? '#B67A20',
            'student_name_color' => $this->normalizeHex($theme['student_name_color'] ?? null) ?? '#0F537F',
            'content_color' => $this->normalizeHex($theme['content_color'] ?? null) ?? '#263A3D',
            'accent_color' => $this->normalizeHex($theme['accent_color'] ?? null) ?? '#086C7C',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function themes(): array
    {
        $themes = config('certificates.themes', []);

        return is_array($themes) && $themes !== [] ? $themes : [
            'blue' => [
                'label' => 'Blue',
                'frame' => 'certificate-frame.svg',
                'heading_color' => '#B67A20',
                'student_name_color' => '#0F537F',
                'content_color' => '#263A3D',
                'accent_color' => '#086C7C',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fonts(): array
    {
        $fonts = config('certificates.fonts', []);

        return is_array($fonts) && $fonts !== [] ? $fonts : [
            'classic' => [
                'label' => 'Classic',
                'body_family' => 'Certificate Naskh',
                'display_family' => 'Certificate Amiri',
            ],
        ];
    }

    private function framePath(string $frame): string
    {
        $filename = basename(str_replace('\\', '/', trim($frame)));

        return 'images/certificate/'.($filename !== '' ? $filename : 'certificate-frame.svg');
    }

    private function gender(?string $gender): string
    {
        return in_array($gender, self::GENDERS, true)
            ? $gender
            : Center::STUDENT_GENDER_MALE;
    }

    private function achievementType(string $achievementType): string
    {
        return in_array($achievementType, self::ACHIEVEMENT_TYPES, true)
            ? $achievementType
            : Certificate::ACHIEVEMENT_SURAH;
    }

    private function versionedAssetUrl(string $relativePath): string
    {
        $path = public_path(ltrim($relativePath, '/\\'));

        return is_file($path)
            ? asset($relativePath).'?v='.filemtime($path)
            : '';
    }

    private function normalizeHex(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1) {
            return null;
        }

        return '#'.strtoupper(substr($value, 1));
    }
}
