<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use function array_filter;
use function array_map;
use function end;
use function explode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function trim;

final readonly class SsLvFieldExtractor
{
    public static function toPlainText(string $description): string
    {
        $normalized = preg_replace('/<br\s*\/?>/iu', "\n", $description);
        $stripped   = strip_tags((string) $normalized);
        $collapsed  = preg_replace('/[ \t]+/', ' ', $stripped);

        return trim((string) $collapsed);
    }

    public static function fieldText(string $plainText, string $label): string
    {
        if (! preg_match('/' . preg_quote($label, '/') . ':\s*(.*?)(?:\n|$)/u', $plainText, $matches)) {
            return '';
        }

        return trim(str_replace(',', '', $matches[1]));
    }

    public static function fieldLastLine(string $plainText, string $label): string
    {
        if (! preg_match('/' . preg_quote($label, '/') . ':\s*(.*?)(?:\n(?=[^\n]+:(?:\s|$))|$)/us', $plainText, $matches)) {
            return '';
        }

        $full  = trim(str_replace(',', '', $matches[1]));
        $lines = array_filter(array_map('trim', explode("\n", $full)));

        return end($lines) ?: '';
    }

    public static function integerField(string $singleLineDescription, string $label): int
    {
        return (int) preg_replace(
            '/\D/u',
            '',
            self::fieldText($singleLineDescription, $label),
        );
    }

    public static function imageUrl(string $description): string|null
    {
        if (! preg_match('/<img\b[^>]*\bsrc=(["\']?)(https?:\/\/[^"\'\s>]+)\1/iu', $description, $matches)) {
            return null;
        }

        return $matches[2];
    }
}
