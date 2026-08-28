<?php

namespace App\Services\System;

use App\Models\Center;
use App\Models\Certificate;

class CertificateWordingService
{
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
    private const TEXT_KEYS = [
        'project_name',
        'intro_before_project',
        'intro_after_center',
        'achievement_intro',
        'achievement_label',
        'achievement_suffix',
        'closing_text',
    ];

    /**
     * Resolve the exact wording to persist when a certificate is issued or redesigned.
     *
     * @return array<string, int|string>
     */
    public function resolve(?string $gender, string $achievementType): array
    {
        $gender = $this->gender($gender);
        $achievementType = $this->achievementType($achievementType);
        $male = config('certificates.wording.male', []);
        $selected = config("certificates.wording.{$gender}", []);
        $male = is_array($male) ? $male : [];
        $selected = is_array($selected) ? $selected : [];

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'student_gender' => $gender,
            'achievement_type' => $achievementType,
            'project_name' => $this->configuredText(
                $selected,
                $male,
                'project_name',
                (string) config('certificates.project_name', ''),
            ),
            'intro_before_project' => $this->configuredText(
                $selected,
                $male,
                'intro_before_project',
                (string) config('certificates.intro_before_project', ''),
            ),
            'intro_after_center' => $this->configuredText(
                $selected,
                $male,
                'intro_after_center',
                (string) config('certificates.intro_after_center', ''),
            ),
            'achievement_intro' => $this->configuredText(
                $selected,
                $male,
                'achievement_intro',
                (string) config('certificates.achievement_intro', ''),
            ),
            'achievement_label' => (string) config("certificates.achievement_labels.{$achievementType}", ''),
            'achievement_suffix' => $this->configuredText(
                $selected,
                $male,
                'achievement_suffix',
                (string) config('certificates.achievement_suffix', ''),
            ),
            'closing_text' => $this->configuredText(
                $selected,
                $male,
                'closing_text',
                (string) config('certificates.closing_text', ''),
            ),
        ];
    }

    /**
     * Normalize an issued snapshot. A saved design gender bridges certificates
     * issued before wording snapshots; certificates without either stay masculine.
     *
     * @param  array<string, mixed>  $legacyOverrides
     * @return array<string, int|string>
     */
    public function snapshot(
        mixed $snapshot,
        string $achievementType,
        array $legacyOverrides = [],
        ?string $fallbackGender = null,
    ): array {
        $fallbackGender = $this->gender($fallbackGender);
        $snapshotGender = is_array($snapshot)
            && in_array($snapshot['student_gender'] ?? null, self::GENDERS, true)
                ? (string) $snapshot['student_gender']
                : $fallbackGender;
        if ($snapshotGender === Center::STUDENT_GENDER_FEMALE) {
            $legacy = $this->resolve($snapshotGender, $achievementType);

            // The old project name was already snapshotted in its own column.
            // Its old closing text was masculine, so it must not override the
            // inferred feminine wording.
            if (is_string($legacyOverrides['project_name'] ?? null)) {
                $legacy['project_name'] = $legacyOverrides['project_name'];
            }
        } else {
            $legacy = $this->legacySnapshot($achievementType, $legacyOverrides);
        }

        if (! is_array($snapshot)) {
            return $legacy;
        }

        $normalized = $legacy;
        $normalized['schema_version'] = is_numeric($snapshot['schema_version'] ?? null)
            ? max(1, (int) $snapshot['schema_version'])
            : self::SCHEMA_VERSION;

        $normalized['student_gender'] = $snapshotGender;

        $normalized['achievement_type'] = $this->achievementType($achievementType);

        foreach (self::TEXT_KEYS as $key) {
            $value = $snapshot[$key] ?? null;
            if (is_string($value) && mb_strlen($value) <= 5000) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, int|string>
     */
    public function legacySnapshot(string $achievementType, array $overrides = []): array
    {
        $legacy = $this->resolve(Center::STUDENT_GENDER_MALE, $achievementType);

        foreach (self::TEXT_KEYS as $key) {
            if (is_string($overrides[$key] ?? null)) {
                $legacy[$key] = $overrides[$key];
            }
        }

        return $legacy;
    }

    /**
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $male
     */
    private function configuredText(array $selected, array $male, string $key, string $fallback): string
    {
        $value = $selected[$key] ?? $male[$key] ?? $fallback;

        return is_scalar($value) ? (string) $value : $fallback;
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
}
