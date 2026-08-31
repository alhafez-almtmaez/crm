<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class GroupWorkingDays
{
    /** @var array<int, string> */
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
    public static function normalize(mixed $workingDays): array
    {
        if (! is_array($workingDays)) {
            return [];
        }

        $allowed = array_flip(self::DAYS);
        $normalized = [];

        foreach ($workingDays as $day) {
            if (! is_string($day)) {
                continue;
            }

            $day = strtolower(trim($day));
            if (isset($allowed[$day])) {
                $normalized[$day] = $day;
            }
        }

        return array_values($normalized);
    }

    public static function isConfigured(mixed $workingDays): bool
    {
        return self::normalize($workingDays) !== [];
    }

    public static function includes(mixed $workingDays, string|CarbonInterface $date): bool
    {
        $normalized = self::normalize($workingDays);
        if ($normalized === []) {
            return false;
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $dayName = self::DAYS[$date->dayOfWeek] ?? null;

        return $dayName !== null && in_array($dayName, $normalized, true);
    }

    /**
     * @return array<string, true>
     */
    public static function lookup(mixed $workingDays): array
    {
        return array_fill_keys(self::normalize($workingDays), true);
    }
}
