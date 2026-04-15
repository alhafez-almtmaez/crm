<?php

namespace App\Support;

class PhoneNumberHelper
{
    public static function normalizeForStorage(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = ltrim($trimmed, '+');

        return $normalized === '' ? null : $normalized;
    }
}
