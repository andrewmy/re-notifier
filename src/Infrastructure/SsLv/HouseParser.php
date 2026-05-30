<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use App\Domain\HouseListing;
use App\Domain\Listing;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Ulid;

use function preg_replace;
use function str_contains;

final readonly class HouseParser implements SsLvParser
{
    public function parse(SsLvRssItem $item): Listing
    {
        $plainText = SsLvFieldExtractor::toPlainText($item->description);

        $street = SsLvFieldExtractor::fieldLastLine($plainText, 'Iela');
        if ($street === '') {
            $street = 'n/a';
        }

        $landAreaRaw = SsLvFieldExtractor::fieldText($plainText, 'Zem. pl.');
        $landArea    = self::normalizeLandArea($landAreaRaw);

        return new HouseListing(
            id: new Ulid(),
            url: $item->url,
            description: $item->description,
            imageUrl: SsLvFieldExtractor::imageUrl($item->description),
            publishedAt: CarbonImmutable::createFromTimeString($item->publishedAt),
            storedAt: CarbonImmutable::now(),
            price: SsLvFieldExtractor::integerField($plainText, 'Cena'),
            rooms: SsLvFieldExtractor::integerField($plainText, 'Ist.'),
            space: SsLvFieldExtractor::integerField($plainText, 'm²'),
            street: $street,
            landArea: $landArea,
            landAreaRaw: $landAreaRaw,
            floors: SsLvFieldExtractor::integerField($plainText, 'Stāvi'),
        );
    }

    private static function normalizeLandArea(string $raw): int
    {
        if ($raw === '') {
            return 0;
        }

        if (str_contains($raw, 'ha')) {
            return (int) ((float) preg_replace('/[^0-9.]/u', '', $raw) * 10000);
        }

        return (int) preg_replace('/\D/u', '', $raw);
    }
}
