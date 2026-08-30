<?php

namespace App\Support;

class NameMasker
{
    public static function mask(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName), 2);

        if ($parts === false || count($parts) < 2) {
            return $parts[0] ?? $fullName;
        }

        $firstName = $parts[0];
        $initial = mb_substr($parts[1], 0, 1);

        return $firstName.' '.$initial.'***';
    }
}
