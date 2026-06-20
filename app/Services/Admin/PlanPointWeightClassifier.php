<?php

namespace App\Services\Admin;

use App\Models\PlanPoint;
use App\Models\PlanWeightRule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PlanPointWeightClassifier
{
    /** @var EloquentCollection<int, PlanWeightRule>|null */
    private ?EloquentCollection $rules = null;

    /**
     * @return array{weight: float, is_standalone: bool, rule_id: ?int, estimated_pages: ?int}
     */
    public function classify(PlanPoint $point): array
    {
        $estimatedPages = $this->estimatedPages($point);
        $text = $this->searchText($point);

        foreach ($this->rules() as $rule) {
            if (! $this->ruleMatches($rule, $text)) {
                continue;
            }

            return [
                'weight' => (float) $rule->weight,
                'is_standalone' => (bool) $rule->is_standalone,
                'rule_id' => (int) $rule->id,
                'estimated_pages' => $estimatedPages,
            ];
        }

        return $this->defaultClassification($point, $text, $estimatedPages);
    }

    public function classifyAndPersist(PlanPoint $point): PlanPoint
    {
        $classification = $this->classify($point);

        $point->forceFill([
            'weight' => $classification['weight'],
            'is_standalone' => $classification['is_standalone'],
            'plan_weight_rule_id' => $classification['rule_id'],
        ])->save();

        return $point->refresh();
    }

    /**
     * @return EloquentCollection<int, PlanWeightRule>
     */
    private function rules(): EloquentCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        return $this->rules = PlanWeightRule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    private function ruleMatches(PlanWeightRule $rule, string $text): bool
    {
        $pattern = trim((string) ($rule->pattern ?? ''));
        if ($pattern === '') {
            return false;
        }

        return $this->patternMatches($pattern, $text);
    }

    private function patternMatches(string $pattern, string $text): bool
    {
        $normalizedPattern = $this->normalize($pattern);
        $regex = '~'.$normalizedPattern.'~u';

        $matches = @preg_match($regex, $text);

        return $matches === 1 || ($matches === false && str_contains($text, $normalizedPattern));
    }

    /**
     * @return array{weight: float, is_standalone: bool, rule_id: ?int, estimated_pages: ?int}
     */
    private function defaultClassification(PlanPoint $point, string $text, ?int $estimatedPages): array
    {
        $weight = (float) ($point->weight ?? 1);
        $isStandalone = (bool) ($point->is_standalone ?? false);

        if ($this->isLargeSurah($point) || ($estimatedPages !== null && $estimatedPages > 15 && ! str_contains($text, 'اسم الجزء'))) {
            $weight = max($weight, 3);
            $isStandalone = true;
        } elseif ($estimatedPages !== null) {
            if ($estimatedPages >= 13) {
                $weight = max($weight, 2);
            } elseif ($estimatedPages >= 4) {
                $weight = max($weight, 1);
            } elseif ($estimatedPages >= 2) {
                $weight = 0;
            } elseif ($estimatedPages === 1) {
                $weight = max($weight, 1);
            }
        }

        return [
            'weight' => $weight,
            'is_standalone' => $isStandalone,
            'rule_id' => null,
            'estimated_pages' => $estimatedPages,
        ];
    }

    private function searchText(PlanPoint $point): string
    {
        return $this->normalize(implode(' ', array_filter([
            $point->name,
            $point->surah_name,
            $point->part_name !== null && trim((string) $point->part_name) !== '' ? 'اسم الجزء' : null,
            $point->part_name,
            $point->three_parts !== null && trim((string) $point->three_parts) !== '' ? 'ثلاثة أجزاء' : null,
            $point->three_parts,
        ], static fn ($value): bool => $value !== null && $value !== '')));
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = str_replace('ة', 'ه', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower($value);
    }

    private function estimatedPages(PlanPoint $point): ?int
    {
        $surahName = $this->normalize((string) ($point->surah_name ?: $point->name));
        foreach ($this->largeSurahPages() as $surah => $pages) {
            if (str_contains($surahName, $surah)) {
                return $pages;
            }
        }

        if ($point->three_parts !== null && trim((string) $point->three_parts) !== '') {
            return 60;
        }

        if ($point->part_name !== null && trim((string) $point->part_name) !== '') {
            return 20;
        }

        $text = $this->searchText($point);
        preg_match_all('/\d+/u', $text, $matches);
        $numbers = array_map(static fn (string $value): int => (int) $value, $matches[0] ?? []);
        $numbers = array_values(array_filter($numbers, static fn (int $value): bool => $value > 0));

        if ($numbers === []) {
            if (str_contains($text, 'صفحه')) {
                return 1;
            }

            return null;
        }

        if (str_contains($text, 'صفحات') || count($numbers) > 1) {
            return max($numbers);
        }

        if (str_contains($text, 'صفحه')) {
            return 1;
        }

        return null;
    }

    private function isLargeSurah(PlanPoint $point): bool
    {
        $surahName = $this->normalize((string) ($point->surah_name ?: $point->name));

        foreach (array_keys($this->largeSurahPages()) as $surah) {
            if (str_contains($surahName, $surah)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    private function largeSurahPages(): array
    {
        return [
            'البقره' => 48,
            'ال عمران' => 27,
            'النساء' => 29,
            'المائده' => 22,
            'الانعام' => 23,
            'الاعراف' => 26,
            'التوبه' => 21,
        ];
    }
}
