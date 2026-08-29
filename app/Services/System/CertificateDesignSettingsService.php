<?php

namespace App\Services\System;

use App\Models\Center;
use App\Models\Certificate;
use App\Models\SystemSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CertificateDesignSettingsService
{
    public const SETTING_KEY = 'certificate_designs';

    private const CACHE_KEY = 'vita_certificate_design_settings';

    private const SETTINGS_SCHEMA_VERSION = 2;

    private const SNAPSHOT_SCHEMA_VERSION = 2;

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
     * The legacy gender-by-achievement defaults are intentionally public. They
     * are also the fallback used for centers that do not have a v2 override.
     *
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
     * Return the legacy gender defaults. Kept as a compatibility API for code
     * that populated the v1 setting directly.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function get(): array
    {
        return $this->state()['defaults'];
    }

    /**
     * Return the effective design matrix for every supplied center.
     *
     * @param  iterable<int, Center|array<string, mixed>>  $centers
     * @return array<int|string, array<string, array<string, string>>>
     */
    public function designsForCenters(iterable $centers): array
    {
        $state = $this->state();
        $designs = [];

        foreach ($centers as $center) {
            $centerId = $this->centerId($center);
            if ($centerId === null) {
                continue;
            }

            $gender = $this->gender($this->centerGender($center));
            $designs[$centerId] = $state['centers'][$centerId]
                ?? $state['defaults'][$gender];
        }

        return $designs;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function designsForCenter(Center|array $center): array
    {
        $centerId = $this->centerId($center);
        $gender = $this->gender($this->centerGender($center));
        $state = $this->state();

        return $centerId !== null && isset($state['centers'][$centerId])
            ? $state['centers'][$centerId]
            : $state['defaults'][$gender];
    }

    /**
     * Update the gender defaults without discarding any center-specific v2
     * overrides. This is primarily a compatibility bridge for v1 callers.
     *
     * @param  array<string, mixed>  $designs
     * @return array<string, array<string, array<string, string>>>
     */
    public function update(array $designs): array
    {
        $this->mutateState(function (array $state) use ($designs): array {
            $state['defaults'] = $this->normalizeDefaults($designs);

            return $state;
        });

        return $this->get();
    }

    /**
     * Atomically update one center without overwriting settings saved by an
     * administrator for any other center.
     *
     * @param  array<string, mixed>  $designs
     * @return array<string, array<string, string>>
     */
    public function updateForCenter(Center $center, array $designs): array
    {
        $centerId = (int) $center->getKey();
        $gender = $this->gender($center->student_gender);

        $this->mutateState(function (array $state) use ($centerId, $gender, $designs): array {
            $state['centers'][$centerId] = $this->normalizeTypeMatrix(
                $designs,
                $gender,
                $state['defaults'],
            );

            return $state;
        });

        return $this->designsForCenter($center);
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
     * Resolve a v1 gender default. New issuance should use resolveForCenter().
     *
     * @return array<string, int|string>
     */
    public function resolve(?string $gender, string $achievementType): array
    {
        $gender = $this->gender($gender);
        $achievementType = $this->achievementType($achievementType);
        $cell = $this->get()[$gender][$achievementType];

        return $this->resolvedSnapshot(null, $gender, $achievementType, $cell);
    }

    /**
     * Resolve the effective center-by-achievement cell to an immutable snapshot.
     * A null center is supported for old certificates whose student no longer
     * belongs to a center.
     *
     * @return array<string, int|string>
     */
    public function resolveForCenter(
        Center|array|null $center,
        string $achievementType,
        ?string $fallbackGender = null,
    ): array {
        $achievementType = $this->achievementType($achievementType);

        if ($center === null) {
            return $this->resolve($fallbackGender, $achievementType);
        }

        $gender = $this->gender($this->centerGender($center));
        $centerId = $this->centerId($center);
        $cell = $this->designsForCenter($center)[$achievementType];

        return $this->resolvedSnapshot($centerId, $gender, $achievementType, $cell);
    }

    /**
     * Resolve a validated, unsaved v1 settings cell for compatibility.
     *
     * @param  array<string, mixed>  $cell
     * @return array<string, int|string>
     */
    public function resolveDraft(?string $gender, string $achievementType, array $cell): array
    {
        $gender = $this->gender($gender);
        $achievementType = $this->achievementType($achievementType);
        $matrix = $this->get();
        $matrix[$gender][$achievementType] = $cell;
        $normalized = $this->normalizeDefaults($matrix)[$gender][$achievementType];

        return $this->resolvedSnapshot(null, $gender, $achievementType, $normalized);
    }

    /**
     * @param  array<string, mixed>  $cell
     * @return array<string, int|string>
     */
    public function resolveDraftForCenter(Center|array $center, string $achievementType, array $cell): array
    {
        $gender = $this->gender($this->centerGender($center));
        $achievementType = $this->achievementType($achievementType);
        $matrix = $this->designsForCenter($center);
        $matrix[$achievementType] = $cell;
        $normalized = $this->normalizeTypeMatrix($matrix, $gender, $this->state()['defaults']);

        return $this->resolvedSnapshot(
            $this->centerId($center),
            $gender,
            $achievementType,
            $normalized[$achievementType],
        );
    }

    /**
     * @param  array<string, string>  $cell
     * @return array<string, int|string>
     */
    private function resolvedSnapshot(
        ?int $centerId,
        string $gender,
        string $achievementType,
        array $cell,
    ): array {
        $theme = $this->themes()[$cell['theme']];
        $font = $this->fonts()[$cell['font']];
        $snapshot = [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
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

        if ($centerId !== null) {
            $snapshot['center_id'] = $centerId;
        }

        return $snapshot;
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
            : self::SNAPSHOT_SCHEMA_VERSION;

        if (is_numeric($snapshot['center_id'] ?? null) && (int) $snapshot['center_id'] > 0) {
            $normalized['center_id'] = (int) $snapshot['center_id'];
        }

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
            'schema_version' => 1,
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
     * @return array{version: int, defaults: array<string, array<string, array<string, string>>>, centers: array<int, array<string, array<string, string>>>}
     */
    private function state(): array
    {
        $storedValue = Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            try {
                $record = SystemSetting::query()
                    ->where('key', self::SETTING_KEY)
                    ->first();
            } catch (QueryException) {
                return [];
            }

            $value = $record?->value;

            return is_array($value) ? $value : [];
        });

        // Normalize after reading from cache so deploy-time theme/font catalog
        // changes are reflected without requiring a manual cache flush.
        return $this->normalizeStoredValue($storedValue);
    }

    /**
     * @return array{version: int, defaults: array<string, array<string, array<string, string>>>, centers: array<int, array<string, array<string, string>>>}
     */
    private function normalizeStoredValue(mixed $value): array
    {
        $stored = is_array($value) ? $value : [];
        $version = is_numeric($stored['version'] ?? null) ? (int) $stored['version'] : 1;

        if ($version >= self::SETTINGS_SCHEMA_VERSION) {
            $defaultSource = $stored['defaults'] ?? [];
            $centerSource = $stored['centers'] ?? [];
        } else {
            // v1 used either {version, designs:{gender:{type:cell}}} or the raw
            // gender matrix. Promote it to v2 defaults without losing a color.
            $defaultSource = $stored['designs'] ?? $stored;
            $centerSource = [];
        }

        $defaults = $this->normalizeDefaults($defaultSource);
        $centerSource = is_array($centerSource) ? $centerSource : [];
        $centerIds = collect(array_keys($centerSource))
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $centerGenders = $this->centerGenders($centerIds);
        $pruneMissingCenters = Schema::hasTable('centers');
        $centers = [];

        foreach ($centerIds as $centerId) {
            if ($pruneMissingCenters && ! isset($centerGenders[$centerId])) {
                continue;
            }

            $gender = $this->gender($centerGenders[$centerId] ?? null);
            $centers[$centerId] = $this->normalizeTypeMatrix(
                $centerSource[$centerId] ?? $centerSource[(string) $centerId] ?? [],
                $gender,
                $defaults,
            );
        }

        return [
            'version' => self::SETTINGS_SCHEMA_VERSION,
            'defaults' => $defaults,
            'centers' => $centers,
        ];
    }

    /**
     * @param  callable(array{version: int, defaults: array<string, array<string, array<string, string>>>, centers: array<int, array<string, array<string, string>>>}): array{version: int, defaults: array<string, array<string, array<string, string>>>, centers: array<int, array<string, array<string, string>>>}  $callback
     */
    private function mutateState(callable $callback): void
    {
        Cache::lock(self::CACHE_KEY.':mutation', 15)->block(10, function () use ($callback): void {
            DB::transaction(function () use ($callback): void {
                $record = SystemSetting::query()
                    ->where('key', self::SETTING_KEY)
                    ->lockForUpdate()
                    ->first();
                $state = $this->normalizeStoredValue($record?->value);
                $state = $callback($state);
                $value = [
                    'version' => self::SETTINGS_SCHEMA_VERSION,
                    'defaults' => $this->normalizeDefaults($state['defaults'] ?? []),
                    'centers' => $this->normalizeCenterOverrides(
                        $state['centers'] ?? [],
                        $state['defaults'] ?? [],
                    ),
                ];

                if ($record === null) {
                    SystemSetting::query()->create([
                        'key' => self::SETTING_KEY,
                        'value' => $value,
                    ]);

                    return;
                }

                $record->forceFill(['value' => $value])->save();
            });
        });

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function normalizeDefaults(mixed $value): array
    {
        $input = is_array($value) ? $value : [];
        $normalized = [];

        foreach (self::GENDERS as $gender) {
            foreach (self::ACHIEVEMENT_TYPES as $achievementType) {
                $normalized[$gender][$achievementType] = $this->normalizeCell(
                    $input[$gender][$achievementType] ?? [],
                    $this->defaultCell($gender),
                );
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $defaults
     * @return array<string, array<string, string>>
     */
    private function normalizeTypeMatrix(mixed $value, string $gender, array $defaults): array
    {
        $input = is_array($value) ? $value : [];
        $normalized = [];

        foreach (self::ACHIEVEMENT_TYPES as $achievementType) {
            $fallback = $defaults[$gender][$achievementType] ?? $this->defaultCell($gender);
            $normalized[$achievementType] = $this->normalizeCell(
                $input[$achievementType] ?? [],
                $fallback,
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $defaults
     * @return array<int, array<string, array<string, string>>>
     */
    private function normalizeCenterOverrides(mixed $centers, array $defaults): array
    {
        $input = is_array($centers) ? $centers : [];
        $centerIds = collect(array_keys($input))
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $centerGenders = $this->centerGenders($centerIds);
        $pruneMissingCenters = Schema::hasTable('centers');
        $normalized = [];

        foreach ($centerIds as $centerId) {
            if ($pruneMissingCenters && ! isset($centerGenders[$centerId])) {
                continue;
            }

            $normalized[$centerId] = $this->normalizeTypeMatrix(
                $input[$centerId] ?? $input[(string) $centerId] ?? [],
                $this->gender($centerGenders[$centerId] ?? null),
                $defaults,
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $fallback
     * @return array<string, string>
     */
    private function normalizeCell(mixed $cell, array $fallback): array
    {
        $cell = is_array($cell) ? $cell : [];
        $themes = $this->themes();
        $fonts = $this->fonts();
        $themeKey = is_string($cell['theme'] ?? null) && isset($themes[$cell['theme']])
            ? $cell['theme']
            : $fallback['theme'];
        $fontKey = is_string($cell['font'] ?? null) && isset($fonts[$cell['font']])
            ? $cell['font']
            : $fallback['font'];
        $theme = $themes[$themeKey];
        $normalized = [
            'theme' => $themeKey,
            'font' => $fontKey,
        ];

        foreach (self::COLOR_KEYS as $colorKey) {
            $normalized[$colorKey] = $this->normalizeHex($cell[$colorKey] ?? null)
                ?? $this->normalizeHex($theme[$colorKey] ?? null)
                ?? $fallback[$colorKey];
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
     * @param  list<int>  $centerIds
     * @return array<int, string>
     */
    private function centerGenders(array $centerIds): array
    {
        if ($centerIds === [] || ! Schema::hasTable('centers')) {
            return [];
        }

        try {
            return Center::query()
                ->whereKey($centerIds)
                ->pluck('student_gender', 'id')
                ->mapWithKeys(static fn (mixed $gender, mixed $id): array => [(int) $id => (string) $gender])
                ->all();
        } catch (QueryException) {
            return [];
        }
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

    private function centerId(Center|array $center): ?int
    {
        $id = $center instanceof Center ? $center->getKey() : ($center['id'] ?? null);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function centerGender(Center|array $center): ?string
    {
        $gender = $center instanceof Center ? $center->student_gender : ($center['student_gender'] ?? null);

        return is_string($gender) ? $gender : null;
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
