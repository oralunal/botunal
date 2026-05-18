<?php

namespace App\Services\Kick;

/**
 * Pure text normalization for the DBD wiki: Turkish-fold + lowercase so
 * "Kum Torbası", "kum torbasi" and "KUM TORBASI" all resolve identically.
 */
class WikiText
{
    private const TR_MAP = [
        'ı' => 'i', 'İ' => 'i', 'I' => 'i',
        'ş' => 's', 'Ş' => 's', 'ç' => 'c', 'Ç' => 'c',
        'ğ' => 'g', 'Ğ' => 'g', 'ö' => 'o', 'Ö' => 'o',
        'ü' => 'u', 'Ü' => 'u',
    ];

    public static function normalize(string $value): string
    {
        $value = strtr($value, self::TR_MAP);
        $value = mb_strtolower($value, 'UTF-8');
        // Remove everything except letters, digits and spaces; collapse multiple spaces.
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    public static function slug(string $type, ?string $owner, string $nameEn): string
    {
        return self::normalize($type.' '.($owner ?? '').' '.$nameEn);
    }
}
