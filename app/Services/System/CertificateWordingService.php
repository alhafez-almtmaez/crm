<?php

namespace App\Services\System;

use App\Models\Center;
use App\Models\Certificate;

class CertificateWordingService
{
    private const SCHEMA_VERSION = 2;

    /**
     * Schema v2 removed the fixed project name from the introduction. The
     * certificate's snapshotted center name is now its complete identity.
     */
    private const CENTER_IDENTITY_SCHEMA_VERSION = 2;

    /** @var list<string> */
    private const GENDERS = [
        Center::STUDENT_GENDER_MALE,
        Center::STUDENT_GENDER_FEMALE,
    ];

    /** @var list<string> */
    private const ACHIEVEMENT_TYPES = Certificate::ACHIEVEMENT_TYPES;

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
        $isSunnah = in_array($achievementType, [
            Certificate::ACHIEVEMENT_SUNNAH_BOOK,
            Certificate::ACHIEVEMENT_SUNNAH_PART,
        ], true);
        $closingKey = $isSunnah ? 'sunnah_closing_text' : 'closing_text';

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'student_gender' => $gender,
            'achievement_type' => $achievementType,
            // Retain the key so old renderers and persisted columns remain
            // compatible, but the center name is now the sole identity shown.
            'project_name' => '',
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
                $closingKey,
                (string) config(
                    $isSunnah ? 'certificates.sunnah_closing_text' : 'certificates.closing_text',
                    '',
                ),
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
        } else {
            $legacy = $this->legacySnapshot($achievementType, $legacyOverrides);
        }

        if (! is_array($snapshot)) {
            return $legacy;
        }

        $snapshotVersion = is_numeric($snapshot['schema_version'] ?? null)
            ? max(1, (int) $snapshot['schema_version'])
            : 1;

        $normalized = $legacy;
        $normalized['schema_version'] = $snapshotVersion;

        $normalized['student_gender'] = $snapshotGender;

        $normalized['achievement_type'] = $this->achievementType($achievementType);

        foreach (self::TEXT_KEYS as $key) {
            $value = $snapshot[$key] ?? null;
            if (is_string($value) && mb_strlen($value) <= 5000) {
                $normalized[$key] = $value;
            }
        }

        $hasLegacyIdentity = $snapshotVersion < self::CENTER_IDENTITY_SCHEMA_VERSION
            || (is_string($snapshot['project_name'] ?? null) && $snapshot['project_name'] !== '')
            || ($snapshot['intro_before_project'] ?? null) === 'تَتَقَدَّمُ إِدَارَةُ مَشْرُوعِ';

        // Normalize only snapshots carrying the old identity contract. A valid
        // v2+ snapshot remains historically stable if wording config changes.
        if ($hasLegacyIdentity) {
            $current = $this->resolve($snapshotGender, $achievementType);
            $normalized['intro_before_project'] = $current['intro_before_project'];
            $normalized['project_name'] = '';
        }

        if ($snapshotVersion < self::CENTER_IDENTITY_SCHEMA_VERSION) {
            $normalized['schema_version'] = self::SCHEMA_VERSION;
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
            if ($key === 'project_name') {
                continue;
            }

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
