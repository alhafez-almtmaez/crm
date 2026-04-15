<?php

namespace App\Services\Admin\AbsenceRules;

use Illuminate\Support\Arr;

class MessageTemplateRenderer
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*}}/', static function (array $matches) use ($context): string {
            $value = Arr::get($context, $matches[1]);

            if ($value === null) {
                return '';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            return '';
        }, $template) ?? $template;
    }
}
