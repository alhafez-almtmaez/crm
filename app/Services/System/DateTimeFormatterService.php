<?php

namespace App\Services\System;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DateTimeFormatterService
{
    public function __construct(private readonly SystemSettingsService $settingsService)
    {
    }

    public function formatForAdmin(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $settings = $this->settingsService->get();
        $timezone = (string) ($settings['timezone'] ?? config('app.timezone'));
        $dateFormat = $this->toPhpDateFormat((string) ($settings['dateFormat'] ?? 'DD/MM/YYYY'));
        $timeFormat = $this->toPhpTimeFormat((string) ($settings['timeFormat'] ?? 'HH:mm'));

        $dateTime = $value instanceof CarbonInterface
            ? $value->copy()
            : Carbon::parse($value);

        return $dateTime
            ->setTimezone($timezone)
            ->format(trim($dateFormat.' '.$timeFormat));
    }

    private function toPhpDateFormat(string $format): string
    {
        return match ($format) {
            'DD/MM/YYYY' => 'd/m/Y',
            'D/M/YYYY' => 'j/n/Y',
            'MM/DD/YYYY' => 'm/d/Y',
            'M/D/YYYY' => 'n/j/Y',
            'YYYY-MM-DD' => 'Y-m-d',
            'DD-MM-YYYY' => 'd-m-Y',
            'MM-DD-YYYY' => 'm-d-Y',
            'DD.MM.YYYY' => 'd.m.Y',
            'MMM D, YYYY' => 'M j, Y',
            'D MMM YYYY' => 'j M Y',
            'MMMM D, YYYY' => 'F j, Y',
            'D MMMM YYYY' => 'j F Y',
            default => 'd/m/Y',
        };
    }

    private function toPhpTimeFormat(string $format): string
    {
        return match ($format) {
            'HH:mm' => 'H:i',
            'HH:mm:ss' => 'H:i:s',
            'HH:mm:ss.SSS' => 'H:i:s.v',
            'hh:mm A' => 'h:i A',
            'hh:mm:ss A' => 'h:i:s A',
            'h:mm A' => 'g:i A',
            'h:mm:ss A' => 'g:i:s A',
            default => 'H:i',
        };
    }
}
