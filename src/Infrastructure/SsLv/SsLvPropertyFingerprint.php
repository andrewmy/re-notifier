<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use App\Domain\Category;

use function implode;
use function preg_replace;
use function trim;

final class SsLvPropertyFingerprint
{
    public static function fromDescription(Category $category, string $description): string|null
    {
        if ($category !== Category::Apartment && $category !== Category::House) {
            return null;
        }

        $plainText = SsLvFieldExtractor::toPlainText($description);
        $street    = self::normalize(SsLvFieldExtractor::fieldLastLine($plainText, 'Iela'));
        $rooms     = SsLvFieldExtractor::integerField($plainText, 'Ist.');
        $space     = SsLvFieldExtractor::integerField($plainText, 'm²');

        if ($street === '' || $street === 'n/a' || $rooms === 0 || $space === 0) {
            return null;
        }

        if ($category === Category::Apartment) {
            $floor = self::normalize(SsLvFieldExtractor::fieldText($plainText, 'Stāvs'));
            if ($floor === '') {
                return null;
            }

            return implode("\x1f", [$category->value, $street, (string) $rooms, (string) $space, $floor]);
        }

        $floors   = SsLvFieldExtractor::integerField($plainText, 'Stāvi');
        $landArea = self::normalize(SsLvFieldExtractor::fieldText($plainText, 'Zem. pl.'));
        if ($floors === 0 || $landArea === '') {
            return null;
        }

        return implode("\x1f", [
            $category->value,
            $street,
            (string) $rooms,
            (string) $space,
            (string) $floors,
            $landArea,
        ]);
    }

    private static function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
