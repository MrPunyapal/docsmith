<?php

declare(strict_types=1);

namespace Docsmith\Support;

final class Color
{
    public static function normalizeHex(string $color, string $fallback): string
    {
        $trimmed = ltrim(trim($color), '#');

        if (strlen($trimmed) === 3) {
            $trimmed = $trimmed[0] . $trimmed[0] . $trimmed[1] . $trimmed[1] . $trimmed[2] . $trimmed[2];
        }

        if (strlen($trimmed) !== 6 || ! ctype_xdigit($trimmed)) {
            return $fallback;
        }

        return '#' . strtolower($trimmed);
    }

    /** @return array{0:int,1:int,2:int} */
    public static function toRgb(string $color): array
    {
        $hex = ltrim(self::normalizeHex($color, '#ff2d20'), '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function rgba(string $color, float $alpha): string
    {
        [$red, $green, $blue] = self::toRgb($color);

        $alpha = rtrim(rtrim(number_format(max(0.0, min(1.0, $alpha)), 2, '.', ''), '0'), '.');

        return sprintf('rgba(%d, %d, %d, %s)', $red, $green, $blue, $alpha);
    }

    public static function mix(string $color, string $mixColor, float $amount): string
    {
        [$red, $green, $blue] = self::toRgb($color);
        [$mixRed, $mixGreen, $mixBlue] = self::toRgb($mixColor);

        $amount = max(0.0, min(1.0, $amount));

        $mixedRed = (int) round($red * (1 - $amount) + $mixRed * $amount);
        $mixedGreen = (int) round($green * (1 - $amount) + $mixGreen * $amount);
        $mixedBlue = (int) round($blue * (1 - $amount) + $mixBlue * $amount);

        return sprintf('#%02x%02x%02x', $mixedRed, $mixedGreen, $mixedBlue);
    }
}
