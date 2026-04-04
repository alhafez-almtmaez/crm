<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSettingsService
{
    private const CACHE_KEY = 'vita_system_settings';
    private const DB_KEY = 'admin_ui';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaultAccent = '#059669';

        return [
            'brandName' => (string) config('app.name', 'Vita'),
            'brandTagline' => 'Internal admin system',
            'logoUrl' => null,
            'logoLightUrl' => null,
            'logoDarkUrl' => null,
            'iconUrl' => null,
            'shape' => 'comfortable',
            'sidebarBehavior' => 'default',
            'fontFamily' => 'instrument',
            'language' => 'en',
            'direction' => 'ltr',
            'timezone' => 'Asia/Amman',
            'dateFormat' => 'DD/MM/YYYY',
            'timeFormat' => 'HH:mm',
            'tokens' => [
                'light' => [
                    'background' => '#ffffff',
                    'foreground' => '#0f172a',
                    'card' => '#fdfdfd',
                    'cardForeground' => '#0f172a',
                    'mutedForeground' => '#6b7280',
                    'border' => '#e8e8e8',
                    'accent' => $defaultAccent,
                ],
                'dark' => [
                    'background' => '#010101',
                    'foreground' => '#e2e8f0',
                    'card' => '#0a0a0a',
                    'cardForeground' => '#e2e8f0',
                    'mutedForeground' => '#a3a3a3',
                    'border' => '#242424',
                    'accent' => $defaultAccent,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        /** @var array<string, mixed> $settings */
        $settings = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $defaults = $this->defaults();

            // Tests and early boot can hit this before migrations are present.
            if (! Schema::hasTable('system_settings')) {
                return $defaults;
            }

            try {
                $record = SystemSetting::query()->where('key', self::DB_KEY)->first();
            } catch (QueryException) {
                return $defaults;
            }

            if (! $record) {
                return $defaults;
            }

            /** @var array<string, mixed> $value */
            $value = is_array($record->value) ? $record->value : [];

            return $this->normalize($value + $defaults);
        });

        return $settings;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $normalized = $this->normalize($input + $this->get());

        SystemSetting::query()->updateOrCreate(
            ['key' => self::DB_KEY],
            ['value' => $normalized],
        );

        Cache::forget(self::CACHE_KEY);

        return $this->get();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalize(array $value): array
    {
        $defaults = $this->defaults();

        $language = ($value['language'] ?? 'en') === 'ar' ? 'ar' : 'en';
        $direction = ($value['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
        $shape = in_array($value['shape'] ?? 'comfortable', ['compact', 'comfortable', 'rounded'], true)
            ? $value['shape']
            : 'comfortable';
        $sidebarBehavior = in_array($value['sidebarBehavior'] ?? 'default', [
            'default',
            'condensed',
            'hidden',
            'small_hover_active',
            'small_hover',
        ], true)
            ? $value['sidebarBehavior']
            : 'default';
        $fontFamily = in_array($value['fontFamily'] ?? 'instrument', [
            'instrument',
            'system',
            'serif',
            'mono',
            'arabic',
            'inter',
            'poppins',
            'manrope',
            'cairo',
            'tajawal',
            'ibm-plex-sans',
            'source-sans-3',
            'nunito',
            'merriweather',
            'fira-sans',
        ], true)
            ? $value['fontFamily']
            : 'instrument';
        $brandName = trim((string) ($value['brandName'] ?? ''));
        $brandTagline = trim((string) ($value['brandTagline'] ?? ''));
        $logoUrl = is_string($value['logoUrl'] ?? null) ? trim((string) $value['logoUrl']) : '';
        $logoLightUrl = is_string($value['logoLightUrl'] ?? null) ? trim((string) $value['logoLightUrl']) : '';
        $logoDarkUrl = is_string($value['logoDarkUrl'] ?? null) ? trim((string) $value['logoDarkUrl']) : '';
        $iconUrl = is_string($value['iconUrl'] ?? null) ? trim((string) $value['iconUrl']) : '';

        $lightAccent = $this->normalizeHex((string) data_get($value, 'tokens.light.accent', ''));
        $darkAccent = $this->normalizeHex((string) data_get($value, 'tokens.dark.accent', ''));
        $accent = $lightAccent ?? $darkAccent ?? data_get($defaults, 'tokens.light.accent');

        $timezone = is_string($value['timezone'] ?? null) ? $value['timezone'] : (string) $defaults['timezone'];
        $dateFormat = is_string($value['dateFormat'] ?? null) ? $value['dateFormat'] : (string) $defaults['dateFormat'];
        $timeFormat = is_string($value['timeFormat'] ?? null) ? $value['timeFormat'] : (string) $defaults['timeFormat'];

        return [
            ...$defaults,
            'brandName' => $brandName !== '' ? $brandName : (string) config('app.name', 'Vita'),
            'brandTagline' => $brandTagline,
            'logoUrl' => $logoUrl !== '' ? $logoUrl : null,
            'logoLightUrl' => $logoLightUrl !== '' ? $logoLightUrl : ($logoUrl !== '' ? $logoUrl : null),
            'logoDarkUrl' => $logoDarkUrl !== '' ? $logoDarkUrl : ($logoUrl !== '' ? $logoUrl : null),
            'iconUrl' => $iconUrl !== '' ? $iconUrl : null,
            'shape' => $shape,
            'sidebarBehavior' => $sidebarBehavior,
            'fontFamily' => $fontFamily,
            'language' => $language,
            'direction' => $direction,
            'timezone' => $timezone,
            'dateFormat' => $dateFormat,
            'timeFormat' => $timeFormat,
            'tokens' => [
                'light' => [
                    ...$defaults['tokens']['light'],
                    'accent' => $accent,
                ],
                'dark' => [
                    ...$defaults['tokens']['dark'],
                    'accent' => $accent,
                ],
            ],
        ];
    }

    private function normalizeHex(string $value): ?string
    {
        $cleaned = ltrim(trim($value), '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $cleaned)) {
            return null;
        }

        return '#'.strtolower($cleaned);
    }
}
