<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use App\Domain\ApartmentListing;
use App\Domain\Listing;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Ulid;

use function preg_match;

final readonly class ApartmentParser implements SsLvParser
{
    public function parse(SsLvRssItem $item): Listing
    {
        $plainText = SsLvFieldExtractor::toPlainText($item->description);

        $street = SsLvFieldExtractor::fieldLastLine($plainText, 'Iela');
        if ($street === '' || preg_match('/.*[<=].*/', $street)) {
            $street = 'n/a';
        }

        return new ApartmentListing(
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
        );
    }
}
