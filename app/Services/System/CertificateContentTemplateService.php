<?php

namespace App\Services\System;

use App\Models\Center;
use App\Models\Certificate;
use App\Models\CertificateContentTemplate;
use App\Models\CertificateContentTemplateAssignment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateContentTemplateService
{
    public const SNAPSHOT_SCHEMA_VERSION = 3;

    public const ALL_ACHIEVEMENT_TYPES = 'all';

    /** @var list<string> */
    public const SECTION_KEYS = [
        'title',
        'quote_first',
        'quote_second',
        'intro',
        'student_line',
        'achievement_line',
        'closing',
    ];

    /** @var list<string> */
    public const ACHIEVEMENT_TYPES = [
        Certificate::ACHIEVEMENT_SURAH,
        Certificate::ACHIEVEMENT_PART,
        Certificate::ACHIEVEMENT_THREE_PARTS,
    ];

    /** @var list<string> */
    public const GENDERS = [
        Center::STUDENT_GENDER_MALE,
        Center::STUDENT_GENDER_FEMALE,
    ];

    /** @var array<string, int> */
    public const SECTION_MAX_LENGTHS = [
        'title' => 120,
        'quote_first' => 180,
        'quote_second' => 180,
        'intro' => 400,
        'student_line' => 160,
        'achievement_line' => 450,
        'closing' => 450,
    ];

    /** @var array<string, string> */
    private const VARIABLES = [
        'student_name' => 'أَحْمَد مُحَمَّد العَبْدُالله',
        'center_name' => 'دار القرآن – مسجد «الصالحين»',
        'achievement_label' => 'سُورَةَ',
        'achievement_name' => 'مريم',
        'certificate_number' => 'HMT-2026-PREVIEW',
        'plan_name' => 'خطة الحافظ المتميز',
        'plan_point_name' => 'إتمام سورة مريم',
        'hijri_date' => '١٥ رَبِيع الأَوَّل ١٤٤٨',
        'gregorian_date' => '٢٠٢٦/٠٨/٢٨',
    ];

    private ?EloquentCollection $assignmentCache = null;

    /** @return list<array{key: string, label: string, description: string, sample: string}> */
    public function variableCatalog(): array
    {
        return collect(self::VARIABLES)
            ->map(static fn (string $sample, string $key): array => [
                'key' => $key,
                'label' => __("certificates.template_variables.{$key}.label"),
                'description' => __("certificates.template_variables.{$key}.description"),
                'sample' => $sample,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $accessibleCenterIds
     * @return list<array<string, mixed>>
     */
    public function templatesPayload(
        bool $activeOnly = false,
        array $accessibleCenterIds = [],
        bool $includeAllAssignments = true,
    ): array {
        return CertificateContentTemplate::query()
            ->withCount(['assignments' => static function ($query) use (
                $accessibleCenterIds,
                $includeAllAssignments,
            ): void {
                if ($includeAllAssignments) {
                    return;
                }

                $query->where(function ($scope) use ($accessibleCenterIds): void {
                    $scope->whereIn('scope_type', [
                        CertificateContentTemplateAssignment::SCOPE_GLOBAL,
                        CertificateContentTemplateAssignment::SCOPE_GENDER,
                    ])->orWhere(function ($centerScope) use ($accessibleCenterIds): void {
                        $centerScope
                            ->where('scope_type', CertificateContentTemplateAssignment::SCOPE_CENTER)
                            ->whereIn('center_id', $accessibleCenterIds);
                    });
                });
            }])
            ->when($activeOnly, static fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (CertificateContentTemplate $template): array => $this->templatePayload($template))
            ->all();
    }

    /** @return array<string, mixed> */
    public function templatePayload(CertificateContentTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'key' => (string) $template->key,
            'name' => (string) $template->name,
            'is_system' => (bool) $template->is_system,
            'is_active' => (bool) $template->is_active,
            'sections' => $this->normalizeSections($template->sections),
            'assignments_count' => (int) ($template->assignments_count
                ?? $template->assignments()->count()),
            'update_url' => route('admin.certificate-content-templates.update', $template),
            'delete_url' => route('admin.certificate-content-templates.destroy', $template),
        ];
    }

    /**
     * @param  list<int>  $accessibleCenterIds
     * @return list<array<string, mixed>>
     */
    public function assignmentsPayload(array $accessibleCenterIds, bool $includeAllCenters): array
    {
        return CertificateContentTemplateAssignment::query()
            ->when(! $includeAllCenters, static function ($query) use ($accessibleCenterIds): void {
                $query->where(function ($scope) use ($accessibleCenterIds): void {
                    $scope->whereIn('scope_type', [
                        CertificateContentTemplateAssignment::SCOPE_GLOBAL,
                        CertificateContentTemplateAssignment::SCOPE_GENDER,
                    ])->orWhere(function ($centerScope) use ($accessibleCenterIds): void {
                        $centerScope
                            ->where('scope_type', CertificateContentTemplateAssignment::SCOPE_CENTER)
                            ->whereIn('center_id', $accessibleCenterIds);
                    });
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (CertificateContentTemplateAssignment $assignment): array => $this->assignmentPayload($assignment))
            ->all();
    }

    /** @return array<string, mixed> */
    public function assignmentPayload(CertificateContentTemplateAssignment $assignment): array
    {
        return [
            'id' => (int) $assignment->id,
            'template_id' => (int) $assignment->template_id,
            'scope_type' => (string) $assignment->scope_type,
            'center_id' => $assignment->center_id !== null ? (int) $assignment->center_id : null,
            'student_gender' => $assignment->student_gender,
            'achievement_type' => $assignment->achievement_type ?? self::ALL_ACHIEVEMENT_TYPES,
            'delete_url' => route('admin.certificate-content-template-assignments.destroy', $assignment),
        ];
    }

    /**
     * @param  iterable<int, Center|array<string, mixed>>  $centers
     * @return array<int|string, array<string, array{template_id: int|null, assignment_id: int|null, source: string}>>
     */
    public function effectiveForCenters(iterable $centers): array
    {
        $effective = [];

        foreach ($centers as $center) {
            $centerId = $this->centerId($center);
            if ($centerId === null) {
                continue;
            }

            foreach (self::ACHIEVEMENT_TYPES as $achievementType) {
                $resolved = $this->resolve($center, $achievementType);
                $effective[$centerId][$achievementType] = [
                    'template_id' => $resolved !== null ? (int) $resolved['template']->id : null,
                    'assignment_id' => $resolved !== null ? (int) $resolved['assignment']->id : null,
                    'source' => $resolved['source'] ?? 'legacy',
                ];
            }
        }

        return $effective;
    }

    /**
     * @return array{template: CertificateContentTemplate, assignment: CertificateContentTemplateAssignment, source: string}|null
     */
    public function resolve(
        Center|array|null $center,
        string $achievementType,
        ?string $fallbackGender = null,
    ): ?array {
        $achievementType = $this->achievementType($achievementType);
        $centerId = $center !== null ? $this->centerId($center) : null;
        $gender = $this->gender(
            ($center !== null ? $this->centerGender($center) : null) ?? $fallbackGender,
        );
        $candidateKeys = [];

        if ($centerId !== null) {
            $candidateKeys[] = ['center_type', $this->scopeKey(
                CertificateContentTemplateAssignment::SCOPE_CENTER,
                $centerId,
                null,
                $achievementType,
            )];
            $candidateKeys[] = ['center_all', $this->scopeKey(
                CertificateContentTemplateAssignment::SCOPE_CENTER,
                $centerId,
                null,
                null,
            )];
        }

        $candidateKeys[] = ['gender_type', $this->scopeKey(
            CertificateContentTemplateAssignment::SCOPE_GENDER,
            null,
            $gender,
            $achievementType,
        )];
        $candidateKeys[] = ['gender_all', $this->scopeKey(
            CertificateContentTemplateAssignment::SCOPE_GENDER,
            null,
            $gender,
            null,
        )];
        $candidateKeys[] = ['global_type', $this->scopeKey(
            CertificateContentTemplateAssignment::SCOPE_GLOBAL,
            null,
            null,
            $achievementType,
        )];
        $candidateKeys[] = ['global_all', $this->scopeKey(
            CertificateContentTemplateAssignment::SCOPE_GLOBAL,
            null,
            null,
            null,
        )];

        $assignments = $this->assignments()->keyBy('scope_key');
        foreach ($candidateKeys as [$source, $scopeKey]) {
            /** @var CertificateContentTemplateAssignment|null $assignment */
            $assignment = $assignments->get($scopeKey);
            if ($assignment === null
                || $assignment->template === null
                || ! $assignment->template->is_active
                || $this->sectionValidationErrors($assignment->template->sections) !== []) {
                continue;
            }

            return [
                'template' => $assignment->template,
                'assignment' => $assignment,
                'source' => $source,
            ];
        }

        return null;
    }

    /**
     * Resolve and freeze the exact template content used by a certificate.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function resolveSnapshot(
        Center|array|null $center,
        string $achievementType,
        array $context,
        ?string $fallbackGender = null,
    ): ?array {
        $resolved = $this->resolve($center, $achievementType, $fallbackGender);
        if ($resolved === null) {
            return null;
        }

        return $this->buildSnapshot(
            $resolved['template']->sections,
            $context,
            $achievementType,
            $this->gender(
                ($center !== null ? $this->centerGender($center) : null) ?? $fallbackGender,
            ),
            $resolved['template'],
            $resolved['source'],
        );
    }

    /**
     * Build an unsaved preview snapshot with exactly the same tokenizer used at issuance.
     *
     * @param  array<string, mixed>  $sections
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function draftSnapshot(
        array $sections,
        array $context,
        string $achievementType,
        ?string $gender,
        ?CertificateContentTemplate $template = null,
        string $source = 'draft',
    ): array {
        $errors = $this->sectionValidationErrors($sections);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->buildSnapshot(
            $sections,
            $context,
            $achievementType,
            $this->gender($gender),
            $template,
            $source,
        );
    }

    /**
     * Normalize a persisted v3 snapshot without consulting live templates.
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(mixed $snapshot): ?array
    {
        if (! is_array($snapshot) || (int) ($snapshot['schema_version'] ?? 0) !== self::SNAPSHOT_SCHEMA_VERSION) {
            return null;
        }

        foreach (['source_sections', 'rendered_sections', 'rendered_segments'] as $requiredKey) {
            if (! is_array($snapshot[$requiredKey] ?? null)) {
                return null;
            }
        }

        foreach (self::SECTION_KEYS as $sectionKey) {
            if (! is_string($snapshot['source_sections'][$sectionKey] ?? null)
                || trim((string) $snapshot['source_sections'][$sectionKey]) === ''
                || ! is_string($snapshot['rendered_sections'][$sectionKey] ?? null)
                || ! is_array($snapshot['rendered_segments'][$sectionKey] ?? null)
                || $snapshot['rendered_segments'][$sectionKey] === []) {
                return null;
            }
        }

        $sourceSections = $this->normalizeSections($snapshot['source_sections'] ?? []);
        $renderedSections = $this->normalizeSections($snapshot['rendered_sections'] ?? []);
        $rawSegments = is_array($snapshot['rendered_segments'] ?? null)
            ? $snapshot['rendered_segments']
            : [];
        $segments = [];

        foreach (self::SECTION_KEYS as $sectionKey) {
            $sectionSegments = is_array($rawSegments[$sectionKey] ?? null)
                ? $rawSegments[$sectionKey]
                : [];
            $segments[$sectionKey] = [];

            foreach ($sectionSegments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $type = ($segment['type'] ?? null) === 'variable' ? 'variable' : 'text';
                $text = is_scalar($segment['text'] ?? null) ? (string) $segment['text'] : '';
                if (mb_strlen($text) > 5000) {
                    continue;
                }

                $normalized = ['type' => $type, 'text' => $text];
                if ($type === 'variable'
                    && is_string($segment['key'] ?? null)
                    && array_key_exists($segment['key'], self::VARIABLES)) {
                    $normalized['key'] = $segment['key'];
                } else {
                    $normalized['type'] = 'text';
                }

                $segments[$sectionKey][] = $normalized;
            }

            if ($segments[$sectionKey] === []) {
                $segments[$sectionKey][] = [
                    'type' => 'text',
                    'text' => $renderedSections[$sectionKey],
                ];
            }
        }

        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'template_id' => is_numeric($snapshot['template_id'] ?? null)
                ? (int) $snapshot['template_id']
                : null,
            'template_key' => is_string($snapshot['template_key'] ?? null)
                ? mb_substr($snapshot['template_key'], 0, 120)
                : null,
            'template_name' => is_string($snapshot['template_name'] ?? null)
                ? mb_substr($snapshot['template_name'], 0, 150)
                : null,
            'template_revision' => is_string($snapshot['template_revision'] ?? null)
                ? mb_substr($snapshot['template_revision'], 0, 64)
                : null,
            'assignment_source' => is_string($snapshot['assignment_source'] ?? null)
                ? mb_substr($snapshot['assignment_source'], 0, 32)
                : 'snapshot',
            'student_gender' => $this->gender(is_string($snapshot['student_gender'] ?? null)
                ? $snapshot['student_gender']
                : null),
            'achievement_type' => $this->achievementType((string) ($snapshot['achievement_type'] ?? '')),
            'source_sections' => $sourceSections,
            'rendered_sections' => $renderedSections,
            'rendered_segments' => $segments,
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?int $userId): CertificateContentTemplate
    {
        $this->validateSections($data['sections'] ?? null);

        $template = CertificateContentTemplate::query()->create([
            'key' => 'custom-'.Str::lower((string) Str::ulid()),
            'name' => trim((string) $data['name']),
            'sections' => $this->normalizeSections($data['sections'] ?? []),
            'is_system' => false,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return $template->loadCount('assignments');
    }

    /** @param array<string, mixed> $data */
    public function update(
        CertificateContentTemplate $template,
        array $data,
        ?int $userId,
    ): CertificateContentTemplate {
        $this->validateSections($data['sections'] ?? null);

        $template->update([
            'name' => trim((string) $data['name']),
            'sections' => $this->normalizeSections($data['sections'] ?? []),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'updated_by' => $userId,
        ]);
        $this->clearAssignmentCache();

        return $template->refresh()->loadCount('assignments');
    }

    public function delete(CertificateContentTemplate $template): void
    {
        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => __('certificates.system_template_cannot_be_deleted'),
            ]);
        }

        if ($template->assignments()->exists()) {
            throw ValidationException::withMessages([
                'template' => __('certificates.assigned_template_cannot_be_deleted'),
            ]);
        }

        $template->delete();
        $this->clearAssignmentCache();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertAssignment(array $data): CertificateContentTemplateAssignment
    {
        $scopeType = (string) $data['scope_type'];
        if (! in_array($scopeType, CertificateContentTemplateAssignment::SCOPES, true)) {
            throw ValidationException::withMessages([
                'scope_type' => __('validation.in', ['attribute' => 'scope_type']),
            ]);
        }

        $centerId = $scopeType === CertificateContentTemplateAssignment::SCOPE_CENTER
            ? (int) ($data['center_id'] ?? 0)
            : null;
        $studentGender = $scopeType === CertificateContentTemplateAssignment::SCOPE_GENDER
            ? (string) ($data['student_gender'] ?? '')
            : null;
        $achievementType = $this->nullableAchievementType($data['achievement_type'] ?? null);
        if ($scopeType === CertificateContentTemplateAssignment::SCOPE_CENTER && $centerId < 1) {
            throw ValidationException::withMessages([
                'center_id' => __('validation.required', ['attribute' => 'center_id']),
            ]);
        }
        if ($scopeType === CertificateContentTemplateAssignment::SCOPE_GENDER
            && ! in_array($studentGender, self::GENDERS, true)) {
            throw ValidationException::withMessages([
                'student_gender' => __('validation.in', ['attribute' => 'student_gender']),
            ]);
        }
        $rawAchievementType = $data['achievement_type'] ?? null;
        if ($rawAchievementType !== null
            && $rawAchievementType !== ''
            && $rawAchievementType !== self::ALL_ACHIEVEMENT_TYPES
            && ! in_array($rawAchievementType, self::ACHIEVEMENT_TYPES, true)) {
            throw ValidationException::withMessages([
                'achievement_type' => __('validation.in', ['attribute' => 'achievement_type']),
            ]);
        }
        $templateExists = CertificateContentTemplate::query()
            ->whereKey((int) ($data['template_id'] ?? 0))
            ->where('is_active', true)
            ->exists();
        if (! $templateExists) {
            throw ValidationException::withMessages([
                'template_id' => __('validation.exists', ['attribute' => 'template_id']),
            ]);
        }
        $scopeKey = $this->scopeKey($scopeType, $centerId, $studentGender, $achievementType);

        $assignment = DB::transaction(function () use (
            $data,
            $scopeType,
            $centerId,
            $studentGender,
            $achievementType,
            $scopeKey,
        ): CertificateContentTemplateAssignment {
            /** @var CertificateContentTemplateAssignment $assignment */
            $assignment = CertificateContentTemplateAssignment::query()->updateOrCreate(
                ['scope_key' => $scopeKey],
                [
                    'template_id' => (int) $data['template_id'],
                    'scope_type' => $scopeType,
                    'center_id' => $centerId,
                    'student_gender' => $studentGender,
                    'achievement_type' => $achievementType,
                ],
            );

            return $assignment;
        });
        $this->clearAssignmentCache();

        return $assignment->refresh();
    }

    public function deleteAssignment(CertificateContentTemplateAssignment $assignment): void
    {
        $assignment->delete();
        $this->clearAssignmentCache();
    }

    public function scopeKey(
        string $scopeType,
        ?int $centerId,
        ?string $studentGender,
        ?string $achievementType,
    ): string {
        $scope = match ($scopeType) {
            CertificateContentTemplateAssignment::SCOPE_CENTER => 'center:'.(string) $centerId,
            CertificateContentTemplateAssignment::SCOPE_GENDER => 'gender:'.(string) $studentGender,
            default => 'global:*',
        };

        return $scope.'|type:'.($achievementType ?? '*');
    }

    /**
     * Return validation errors keyed like a Laravel form request.
     *
     * @return array<string, list<string>>
     */
    public function sectionValidationErrors(mixed $sections, string $prefix = 'sections'): array
    {
        if (! is_array($sections)) {
            return [$prefix => [__('validation.array', ['attribute' => $prefix])]];
        }

        $errors = [];
        $extraKeys = array_diff(array_keys($sections), self::SECTION_KEYS);
        foreach ($extraKeys as $key) {
            $errors["{$prefix}.{$key}"][] = __('validation.prohibited', ['attribute' => "{$prefix}.{$key}"]);
        }

        foreach (self::SECTION_KEYS as $sectionKey) {
            $path = "{$prefix}.{$sectionKey}";
            $value = $sections[$sectionKey] ?? null;
            if (! is_string($value) || trim($value) === '') {
                $errors[$path][] = __('validation.required', ['attribute' => $path]);

                continue;
            }

            if (mb_strlen($value) > self::SECTION_MAX_LENGTHS[$sectionKey]) {
                $errors[$path][] = __('validation.max.string', [
                    'attribute' => $path,
                    'max' => self::SECTION_MAX_LENGTHS[$sectionKey],
                ]);
            }

            if ($this->containsMarkupOrBlade($value)) {
                $errors[$path][] = __('certificates.template_plain_text_only');
            }

            foreach ($this->unknownVariables($value) as $variable) {
                $errors[$path][] = __('certificates.template_unknown_variable', ['variable' => $variable]);
            }
        }

        foreach ([
            'intro' => ['center_name'],
            'student_line' => ['student_name'],
            'achievement_line' => ['achievement_label', 'achievement_name'],
        ] as $sectionKey => $requiredVariables) {
            $section = is_string($sections[$sectionKey] ?? null) ? $sections[$sectionKey] : '';
            foreach ($requiredVariables as $requiredVariable) {
                if ($this->containsVariable($section, $requiredVariable)) {
                    continue;
                }

                $errors["{$prefix}.{$sectionKey}"][] = __('certificates.template_required_variable', [
                    'variable' => '{{ '.$requiredVariable.' }}',
                ]);
            }
        }

        return $errors;
    }

    /** @return array<string, string> */
    public function normalizeSections(mixed $sections): array
    {
        $input = is_array($sections) ? $sections : [];

        return collect(self::SECTION_KEYS)->mapWithKeys(static function (string $key) use ($input): array {
            $value = is_string($input[$key] ?? null) ? $input[$key] : '';
            $value = str_replace(["\r\n", "\r"], "\n", $value);

            return [$key => trim($value)];
        })->all();
    }

    /**
     * Recreate the v2 layout as a safe v3-shaped draft for design previews only.
     * Issued legacy certificates continue through their original renderer.
     *
     * @return array<string, string>
     */
    public function legacySections(?string $gender, string $achievementType): array
    {
        $gender = $this->gender($gender);
        $achievementType = $this->achievementType($achievementType);
        $selected = config("certificates.wording.{$gender}", []);
        $selected = is_array($selected) ? $selected : [];

        return [
            'title' => (string) config('certificates.title', ''),
            'quote_first' => (string) config('certificates.quote_first', ''),
            'quote_second' => (string) config('certificates.quote_second', ''),
            'intro' => trim(implode(' ', array_filter([
                (string) ($selected['intro_before_project'] ?? config('certificates.intro_before_project', '')),
                '{{ center_name }}',
                (string) ($selected['intro_after_center'] ?? config('certificates.intro_after_center', '')),
            ], static fn (string $value): bool => trim($value) !== ''))),
            'student_line' => '﴿ {{ student_name }} ﴾',
            'achievement_line' => trim(implode(' ', array_filter([
                (string) ($selected['achievement_intro'] ?? config('certificates.achievement_intro', '')),
                '{{ achievement_label }}',
                '﴿ {{ achievement_name }} ﴾',
                (string) ($selected['achievement_suffix'] ?? config('certificates.achievement_suffix', '')),
            ], static fn (string $value): bool => trim($value) !== ''))),
            'closing' => (string) ($selected['closing_text'] ?? config('certificates.closing_text', '')),
        ];
    }

    /** @return EloquentCollection<int, CertificateContentTemplateAssignment> */
    private function assignments(): EloquentCollection
    {
        return $this->assignmentCache ??= CertificateContentTemplateAssignment::query()
            ->with('template')
            ->orderBy('id')
            ->get();
    }

    private function clearAssignmentCache(): void
    {
        $this->assignmentCache = null;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildSnapshot(
        array $sections,
        array $context,
        string $achievementType,
        string $gender,
        ?CertificateContentTemplate $template,
        string $source,
    ): array {
        $sourceSections = $this->normalizeSections($sections);
        $variables = collect(array_keys(self::VARIABLES))->mapWithKeys(static function (string $key) use ($context): array {
            $value = $context[$key] ?? '';

            return [$key => is_scalar($value) ? (string) $value : ''];
        })->all();
        $renderedSections = [];
        $renderedSegments = [];

        foreach ($sourceSections as $sectionKey => $section) {
            $renderedSegments[$sectionKey] = $this->compile($section, $variables);
            $renderedSections[$sectionKey] = collect($renderedSegments[$sectionKey])
                ->pluck('text')
                ->implode('');
        }

        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'template_id' => $template !== null ? (int) $template->id : null,
            'template_key' => $template?->key,
            'template_name' => $template?->name,
            'template_revision' => hash('sha256', json_encode(
                $sourceSections,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )),
            'assignment_source' => $source,
            'student_gender' => $gender,
            'achievement_type' => $this->achievementType($achievementType),
            'source_sections' => $sourceSections,
            'rendered_sections' => $renderedSections,
            'rendered_segments' => $renderedSegments,
        ];
    }

    /**
     * @param  array<string, string>  $variables
     * @return list<array{type: string, text: string, key?: string}>
     */
    private function compile(string $source, array $variables): array
    {
        $parts = preg_split(
            '/(\{\{\s*[a-z_]+\s*\}\})/u',
            $source,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];
        $segments = [];

        foreach ($parts as $part) {
            if (preg_match('/^\{\{\s*([a-z_]+)\s*\}\}$/u', $part, $matches) === 1
                && array_key_exists($matches[1], $variables)) {
                $segments[] = [
                    'type' => 'variable',
                    'key' => $matches[1],
                    'text' => $variables[$matches[1]],
                ];

                continue;
            }

            $segments[] = ['type' => 'text', 'text' => $part];
        }

        return $segments;
    }

    /** @return list<string> */
    private function unknownVariables(string $value): array
    {
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/u', $value, $matches);
        $unknown = collect($matches[1] ?? [])
            ->map(static fn (mixed $name): string => trim((string) $name))
            ->reject(static fn (string $name): bool => array_key_exists($name, self::VARIABLES))
            ->unique()
            ->values()
            ->all();

        $withoutKnownTokens = preg_replace('/\{\{\s*[a-z_]+\s*\}\}/u', '', $value) ?? $value;
        if (str_contains($withoutKnownTokens, '{{') || str_contains($withoutKnownTokens, '}}')) {
            $unknown[] = __('certificates.template_malformed_variable');
        }

        return array_values(array_unique($unknown));
    }

    private function containsVariable(string $value, string $variable): bool
    {
        return preg_match('/\{\{\s*'.preg_quote($variable, '/').'\s*\}\}/u', $value) === 1;
    }

    private function containsMarkupOrBlade(string $value): bool
    {
        return preg_match('/<\/?[a-z][^>]*>/iu', $value) === 1
            || str_contains($value, '{!!')
            || str_contains($value, '!!}')
            || preg_match('/@(php|if|unless|foreach|for|while|switch|include|extends|section|yield|auth|guest|can|cannot|error|verbatim|once|push|stack|vite)\b/iu', $value) === 1;
    }

    private function validateSections(mixed $sections): void
    {
        $errors = $this->sectionValidationErrors($sections);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function nullableAchievementType(mixed $achievementType): ?string
    {
        if ($achievementType === null || $achievementType === '' || $achievementType === self::ALL_ACHIEVEMENT_TYPES) {
            return null;
        }

        return in_array($achievementType, self::ACHIEVEMENT_TYPES, true)
            ? (string) $achievementType
            : null;
    }

    private function achievementType(string $achievementType): string
    {
        return in_array($achievementType, self::ACHIEVEMENT_TYPES, true)
            ? $achievementType
            : Certificate::ACHIEVEMENT_SURAH;
    }

    private function gender(?string $gender): string
    {
        return in_array($gender, self::GENDERS, true)
            ? $gender
            : Center::STUDENT_GENDER_MALE;
    }

    private function centerId(Center|array $center): ?int
    {
        $id = $center instanceof Center ? $center->getKey() : Arr::get($center, 'id');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function centerGender(Center|array $center): ?string
    {
        $gender = $center instanceof Center ? $center->student_gender : Arr::get($center, 'student_gender');

        return is_string($gender) ? $gender : null;
    }
}
