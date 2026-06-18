<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '261')) {
            return '0' . substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            return $digits;
        }

        return $digits;
    }
}
