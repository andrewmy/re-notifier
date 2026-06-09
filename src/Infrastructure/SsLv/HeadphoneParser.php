<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use App\Domain\HeadphoneListing;
use App\Domain\Listing;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Ulid;

final readonly class HeadphoneParser implements SsLvParser
{
    public function parse(SsLvRssItem $item): Listing
    {
        $plainText = SsLvFieldExtractor::toPlainText($item->description);

        return new HeadphoneListing(
            id: new Ulid(),
            url: $item->url,
            description: $item->description,
            imageUrl: SsLvFieldExtractor::imageUrl($item->description),
            publishedAt: CarbonImmutable::createFromTimeString($item->publishedAt),
            storedAt: CarbonImmutable::now(),
            price: SsLvFieldExtractor::integerField($plainText, 'Cena'),
            brand: SsLvFieldExtractor::fieldText($plainText, 'Marka'),
            condition: SsLvFieldExtractor::fieldText($plainText, 'Stāv.'),
            title: $item->title,
        );
    }
}
