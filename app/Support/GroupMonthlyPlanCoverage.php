<?php

namespace App\Support;

use App\Models\MonthlyPlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class GroupMonthlyPlanCoverage
{
    public static function exists(int $groupId, string|CarbonInterface $date): bool
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $dateString = $date->toDateString();

        return MonthlyPlan::query()
            ->where('group_id', $groupId)
            ->where(function ($coverage) use ($date, $dateString): void {
                $coverage
                    ->where(function ($bounded) use ($dateString): void {
                        $bounded
                            ->whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->whereDate('start_date', '<=', $dateString)
                            ->whereDate('end_date', '>=', $dateString);
                    })
                    ->orWhere(function ($legacy) use ($date): void {
                        $legacy
                            ->where(function ($missingBoundary): void {
                                $missingBoundary
                                    ->whereNull('start_date')
                                    ->orWhereNull('end_date');
                            })
                            ->where('year', $date->year)
                            ->where('month', $date->month);
                    });
            })
            ->exists();
    }
}
