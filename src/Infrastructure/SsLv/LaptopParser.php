<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use App\Domain\LaptopListing;
use App\Domain\Listing;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Ulid;

final readonly class LaptopParser implements SsLvParser
{
    public function parse(SsLvRssItem $item): Listing
    {
        $plainText = SsLvFieldExtractor::toPlainText($item->description);

        return new LaptopListing(
            id: new Ulid(),
            url: $item->url,
            description: $item->description,
            imageUrl: SsLvFieldExtractor::imageUrl($item->description),
            publishedAt: CarbonImmutable::createFromTimeString($item->publishedAt),
            storedAt: CarbonImmutable::now(),
            price: SsLvFieldExtractor::integerField($plainText, 'Cena'),
            brand: SsLvFieldExtractor::fieldText($plainText, 'Marka'),
            model: SsLvFieldExtractor::fieldText($plainText, 'Modelis'),
            displayInches: SsLvFieldExtractor::integerField($plainText, 'Displejs'),
            storageGb: SsLvFieldExtractor::integerField($plainText, 'HDD'),
            ramGb: SsLvFieldExtractor::integerField($plainText, 'RAM'),
            title: $item->title,
        );
    }
}
