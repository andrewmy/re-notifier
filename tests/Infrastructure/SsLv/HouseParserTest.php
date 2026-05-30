<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

use App\Domain\Category;
use App\Domain\Listing;
use App\Infrastructure\SsLv\HouseParser;
use App\Infrastructure\SsLv\SsLvRssItem;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

final class HouseParserTest extends TestCase
{
    public function testParsesHouseListing(): void
    {
        $listing = $this->parse(SsLvDescription::house(
            street: 'Babites',
            rooms: 5,
            space: 120,
            landArea: '1 200',
            landUnit: 'm²',
            floors: 2,
            price: '180,000 €',
        ));

        self::assertSame('https://www.ss.lv/msg/lv/real-estate/homes-summer-residences/riga-region/babites/example.html', $listing->url);
        self::assertSame(180000, $listing->price);
        self::assertSame(Category::House, $listing->category);
        self::assertSame(5, $listing->parsedFields['rooms']);
        self::assertSame(120, $listing->parsedFields['space']);
        self::assertSame('Babites', $listing->parsedFields['street']);
        self::assertSame(1200, $listing->parsedFields['landArea']);
        self::assertSame(2, $listing->parsedFields['floors']);
    }

    public function testLandAreaInHectaresNormalizedToSquareMeters(): void
    {
        $listing = $this->parse(SsLvDescription::house(
            landArea: '0.5',
            landUnit: 'ha.',
        ));

        self::assertSame(5000, $listing->parsedFields['landArea']);
        self::assertSame('0.5 ha.', $listing->parsedFields['landAreaRaw']);
    }

    private function parse(string $description, string $url = 'https://www.ss.lv/msg/lv/real-estate/homes-summer-residences/riga-region/babites/example.html'): Listing
    {
        return (new HouseParser())->parse(new SsLvRssItem(
            publishedAt: 'Thu, 28 May 2026 16:38:34 +0300',
            url: $url,
            description: $description,
        ));
    }
}
