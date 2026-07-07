<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DailyWeightLimits
{
    /**
     * @var array<int, string>
     */
    private const DAYS = [
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
    ];

    /**
     * @return array<int, string>
     */
    public static function days(): array
    {
        return self::DAYS;
    }

    /**
     * @param  mixed  $workingDays
     * @return array<int, string>
     */
    public static function normalizeWorkingDays(mixed $workingDays): array
    {
        if (! is_array($workingDays)) {
            return self::DAYS;
        }

        $validDays = array_flip(self::DAYS);
        $normalized = [];

        foreach ($workingDays as $day) {
            if (! is_string($day)) {
                continue;
            }

            $day = strtolower(trim($day));
            if (isset($validDays[$day]) && ! in_array($day, $normalized, true)) {
                $normalized[] = $day;
            }
        }

        return $normalized === [] ? self::DAYS : $normalized;
    }

    /**
     * @param  mixed  $limits
     * @param  array<int, string>|null  $workingDays
     * @return array<string, int>
     */
    public static function normalize(mixed $limits, int|float|string|null $fallback, ?array $workingDays = null): array
    {
        $fallback = self::normalizeLimit($fallback);
        $workingDays = self::normalizeWorkingDays($workingDays ?? self::DAYS);
        $limits = is_array($limits) ? $limits : [];
        $normalized = [];

        foreach ($workingDays as $day) {
            $normalized[$day] = self::normalizeLimit($limits[$day] ?? $fallback);
        }

        return $normalized;
    }

    /**
     * @param  array<string, int|float|string>|null  $limits
     */
    public static function limitForDate(?array $limits, CarbonInterface $date, int|float|string|null $fallback): int
    {
        $day = self::DAYS[$date->dayOfWeek] ?? null;
        if ($day === null) {
            return self::normalizeLimit($fallback);
        }

        return self::normalizeLimit($limits[$day] ?? $fallback);
    }

    public static function normalizeLimit(int|float|string|null $value): int
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return 2;
        }

        $limit = (int) $value;

        return max(1, min($limit, 99));
    }
}
