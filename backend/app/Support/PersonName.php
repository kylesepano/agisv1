<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalizes structured person-name parts and produces consistent display names.
 */
class PersonName
{
    /** @return array{first_name: string, middle_name: ?string, last_name: string, name_extension: ?string, name: string, initials: string} */
    public static function fromParts(
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?string $extension = null,
    ): array {
        $firstName = self::normalizePart($firstName);
        $middleName = self::normalizeMiddleInitial($middleName);
        $lastName = self::normalizePart($lastName);
        $extension = self::normalizeExtension($extension);
        $name = collect([$firstName, $middleName, $lastName, $extension])
            ->filter()
            ->implode(' ');

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'name_extension' => $extension,
            'name' => $name,
            'initials' => mb_strtoupper(
                mb_substr($firstName, 0, 1).mb_substr($lastName, 0, 1),
            ),
        ];
    }

    /** @return array{first_name: string, middle_name: ?string, last_name: string, name_extension: ?string, name: string, initials: string} */
    public static function parse(string $name): array
    {
        $name = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($name)) ?? trim($name);
        $name = preg_replace('/,\s*.*$/u', '', $name) ?? $name;
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->values();
        $extension = null;
        $honorifics = ['atty', 'engr', 'dr', 'judge', 'hon', 'mr', 'mrs', 'ms', 'arch'];

        while (
            $parts->isNotEmpty()
            && in_array(mb_strtolower(rtrim((string) $parts->first(), '.')), $honorifics, true)
        ) {
            $parts->shift();
        }

        if ($parts->count() > 1 && preg_match('/^(Jr\.?|Sr\.?|II|III|IV|V)$/i', (string) $parts->last())) {
            $extension = (string) $parts->pop();
        }

        $lastName = (string) ($parts->pop() ?? 'Unknown');
        $middleName = null;

        if ($parts->count() > 1 && preg_match('/^\pL\.?$/u', (string) $parts->last())) {
            $middleName = (string) $parts->pop();
        }

        $firstName = $parts->isEmpty() ? $lastName : $parts->implode(' ');

        return self::fromParts($firstName, $middleName, $lastName, $extension);
    }

    private static function normalizePart(string $value): string
    {
        $normalized = Str::of($value)
            ->squish()
            ->lower()
            ->title()
            ->toString();

        return collect(preg_split('/\s+/', $normalized) ?: [])
            ->map(fn (string $part): string => in_array(
                mb_strtolower($part),
                ['de', 'del', 'dela', 'la', 'van', 'von'],
                true,
            ) ? mb_strtolower($part) : $part)
            ->implode(' ');
    }

    private static function normalizeMiddleInitial(?string $value): ?string
    {
        if (! preg_match('/\pL/u', (string) $value, $matches)) {
            return null;
        }

        return mb_strtoupper($matches[0]).'.';
    }

    private static function normalizeExtension(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match (strtoupper(rtrim($value, '.'))) {
            'JR' => 'Jr.',
            'SR' => 'Sr.',
            'II', 'III', 'IV', 'V' => strtoupper($value),
            default => Str::title(Str::lower($value)),
        };
    }
}
