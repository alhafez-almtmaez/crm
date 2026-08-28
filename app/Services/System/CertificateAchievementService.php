<?php

namespace App\Services\System;

use App\Models\Certificate;
use App\Models\PlanPoint;

class CertificateAchievementService
{
    /** @var list<string> */
    private const TYPES = [
        Certificate::ACHIEVEMENT_SURAH,
        Certificate::ACHIEVEMENT_PART,
        Certificate::ACHIEVEMENT_THREE_PARTS,
    ];

    /**
     * Resolve a plan point exactly as certificate issuance does. The broadest
     * milestone wins when imported data contains more than one achievement field.
     *
     * @return array{type: string, name: string}|null
     */
    public function resolve(PlanPoint $point): ?array
    {
        $threeParts = $this->nullableTrim($point->three_parts);
        if ($threeParts !== null) {
            return ['type' => Certificate::ACHIEVEMENT_THREE_PARTS, 'name' => $threeParts];
        }

        $partName = $this->nullableTrim($point->part_name);
        if ($partName !== null) {
            return ['type' => Certificate::ACHIEVEMENT_PART, 'name' => $partName];
        }

        $surahName = $this->nullableTrim($point->surah_name);
        if ($surahName !== null) {
            return ['type' => Certificate::ACHIEVEMENT_SURAH, 'name' => $surahName];
        }

        return null;
    }

    /**
     * @return array<string, list<array{id: int, achievement_type: string, achievement_name: string, plan_name: string, plan_point_name: string}>>
     */
    public function previewAchievements(): array
    {
        $grouped = array_fill_keys(self::TYPES, []);
        $points = PlanPoint::query()
            ->select('plan_points.*')
            ->join('plan_types', 'plan_types.id', '=', 'plan_points.plan_id')
            ->with('plan:id,name')
            ->where('plan_points.requires_certificate', true)
            ->where(function ($query): void {
                $query->whereNotNull('plan_points.surah_name')
                    ->orWhereNotNull('plan_points.part_name')
                    ->orWhereNotNull('plan_points.three_parts');
            })
            ->orderBy('plan_types.name')
            ->orderBy('plan_types.id')
            ->orderBy('plan_points.sort_order')
            ->orderBy('plan_points.id')
            ->get();

        foreach ($points as $point) {
            $option = $this->previewAchievement($point);
            if ($option !== null) {
                $grouped[$option['achievement_type']][] = $option;
            }
        }

        return $grouped;
    }

    /**
     * @return array{id: int, achievement_type: string, achievement_name: string, plan_name: string, plan_point_name: string}|null
     */
    public function findPreviewAchievement(int $planPointId): ?array
    {
        $point = PlanPoint::query()
            ->with('plan:id,name')
            ->whereKey($planPointId)
            ->where('requires_certificate', true)
            ->first();

        return $point !== null ? $this->previewAchievement($point) : null;
    }

    /**
     * @return array{id: int, achievement_type: string, achievement_name: string, plan_name: string, plan_point_name: string}|null
     */
    public function previewAchievement(PlanPoint $point): ?array
    {
        if (! $point->requires_certificate) {
            return null;
        }

        $achievement = $this->resolve($point);
        if ($achievement === null) {
            return null;
        }

        $point->loadMissing('plan:id,name');
        if ($point->plan === null) {
            return null;
        }

        return [
            'id' => (int) $point->id,
            'achievement_type' => $achievement['type'],
            'achievement_name' => $this->displayName($achievement['type'], $achievement['name']),
            'plan_name' => trim((string) $point->plan->name),
            'plan_point_name' => trim((string) $point->name),
        ];
    }

    /**
     * Plan fields sometimes include words such as "الجزء" while the certificate
     * already prints the type label. Remove only that duplicated display prefix.
     */
    public function displayName(string $type, string $name): string
    {
        $name = trim($name);
        $words = preg_split('/\s+/u', $name) ?: [];
        if ($words === []) {
            return $name;
        }

        $plain = array_map(static function (string $word): string {
            $withoutMarks = preg_replace('/\p{Mn}+/u', '', $word) ?? $word;

            return str_replace('ـ', '', $withoutMarks);
        }, $words);
        $prefixLength = match ($type) {
            Certificate::ACHIEVEMENT_SURAH => ($plain[0] ?? '') === 'سورة' ? 1 : 0,
            Certificate::ACHIEVEMENT_PART => in_array($plain[0] ?? '', ['جزء', 'الجزء'], true) ? 1 : 0,
            Certificate::ACHIEVEMENT_THREE_PARTS => $this->threePartsPrefixLength($plain),
            default => 0,
        };
        $displayWords = array_slice($words, $prefixLength);

        return $displayWords !== [] ? implode(' ', $displayWords) : $name;
    }

    /**
     * @param  list<string>  $words
     */
    private function threePartsPrefixLength(array $words): int
    {
        $first = $words[0] ?? '';
        $second = $words[1] ?? '';

        if (in_array($first, ['الأجزاء', 'الاجزاء'], true)
            && in_array($second, ['الثلاثة', 'ثلاثة'], true)) {
            return 2;
        }

        return $first === 'ثلاثة' && in_array($second, ['أجزاء', 'اجزاء'], true) ? 2 : 0;
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
