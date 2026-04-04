<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dateFormats = [
            'DD/MM/YYYY',
            'D/M/YYYY',
            'MM/DD/YYYY',
            'M/D/YYYY',
            'YYYY-MM-DD',
            'DD-MM-YYYY',
            'MM-DD-YYYY',
            'DD.MM.YYYY',
            'MMM D, YYYY',
            'D MMM YYYY',
            'MMMM D, YYYY',
            'D MMMM YYYY',
        ];

        $timeFormats = [
            'HH:mm',
            'HH:mm:ss',
            'HH:mm:ss.SSS',
            'hh:mm A',
            'hh:mm:ss A',
            'h:mm A',
            'h:mm:ss A',
        ];

        return [
            'brandName' => ['required', 'string', 'max:80'],
            'brandTagline' => ['nullable', 'string', 'max:140'],
            'logoUrl' => ['nullable', 'string', 'max:2048'],
            'logoLightUrl' => ['nullable', 'string', 'max:2048'],
            'logoDarkUrl' => ['nullable', 'string', 'max:2048'],
            'iconUrl' => ['nullable', 'string', 'max:2048'],
            'shape' => ['required', Rule::in(['compact', 'comfortable', 'rounded'])],
            'sidebarBehavior' => ['required', Rule::in(['default', 'condensed', 'hidden', 'small_hover_active', 'small_hover'])],
            'fontFamily' => ['required', Rule::in([
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
            ])],
            'language' => ['required', Rule::in(['en', 'ar'])],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'timezone' => ['required', 'timezone'],
            'dateFormat' => ['required', Rule::in($dateFormats)],
            'timeFormat' => ['required', Rule::in($timeFormats)],
            'tokens' => ['required', 'array'],
            'tokens.light' => ['required', 'array'],
            'tokens.dark' => ['required', 'array'],
            'tokens.light.accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tokens.dark.accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
