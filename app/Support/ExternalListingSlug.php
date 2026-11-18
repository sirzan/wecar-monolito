<?php

namespace App\Support;

use Illuminate\Support\Str;

class ExternalListingSlug
{
    public static function makeFromTitle(?string $title): ?string
    {
        if (! $title) {
            return null;
        }

        $tokens = preg_split('/\s+/u', trim($title)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token) => $token !== '' && ! preg_match('/^(19|20)\d{2}$/', $token)));

        return isset($tokens[0]) ? Str::slug($tokens[0]) : null;
    }

    public static function modelFromTitle(?string $title, ?string $make): ?string
    {
        if (! $title) {
            return null;
        }

        $tokens = preg_split('/\s+/u', trim($title)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token) => $token !== '' && ! preg_match('/^(19|20)\d{2}$/', $token)));

        if ($make !== null && isset($tokens[0]) && Str::slug($tokens[0]) === $make) {
            array_shift($tokens);
        }

        $model = Str::slug(implode(' ', array_slice($tokens, 0, 4)));

        return $model !== '' ? $model : null;
    }

    public static function make(?int $year, ?string $title, string $externalId): string
    {
        $make = self::makeFromTitle($title);
        $model = self::modelFromTitle($title, $make);

        return Str::slug(implode('-', array_filter([
            $year ?: 'sin-anio',
            $make ?: 'auto',
            $model ?: 'externo',
            $externalId,
        ])));
    }
}
