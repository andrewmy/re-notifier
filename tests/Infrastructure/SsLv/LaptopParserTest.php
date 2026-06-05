<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

use App\Domain\Category;
use App\Domain\LaptopListing;
use App\Infrastructure\SsLv\LaptopParser;
use App\Infrastructure\SsLv\SsLvRssItem;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

final class LaptopParserTest extends TestCase
{
    public function testParsesLaptopListing(): void
    {
        $listing = (new LaptopParser())->parse(new SsLvRssItem(
            publishedAt: 'Fri, 05 Jun 2026 10:48:59 +0300',
            url: 'https://www.ss.lv/msg/lv/electronics/computers/noutbooks/fxlge.html',
            description: SsLvDescription::laptop(
                brand: 'Apple',
                model: 'Air',
                display: '13"',
                storage: 256,
                ram: 16,
                price: '899 €',
            ),
            title: 'Pārdodu Apple Macbook Air M4. Lietots dažas reizes.',
        ));

        self::assertInstanceOf(LaptopListing::class, $listing);
        self::assertSame(Category::Laptop, $listing->category);
        self::assertSame(899, $listing->price);
        self::assertSame('Apple', $listing->parsedFields['brand']);
        self::assertSame('Air', $listing->parsedFields['model']);
        self::assertSame(13, $listing->parsedFields['displayInches']);
        self::assertSame(256, $listing->parsedFields['storageGb']);
        self::assertSame(16, $listing->parsedFields['ramGb']);
        self::assertSame('Pārdodu Apple Macbook Air M4. Lietots dažas reizes.', $listing->parsedFields['title']);
    }

    public function testParsesNestedBoldBrandAndModelFirstLines(): void
    {
        $listing = (new LaptopParser())->parse(new SsLvRssItem(
            publishedAt: 'Fri, 05 Jun 2026 11:32:31 +0300',
            url: 'https://www.ss.lv/msg/lv/electronics/computers/noutbooks/fbeec.html',
            description: SsLvDescription::laptop(
                brand: '<b>Apple<br>Air</b>',
                model: '<b>Air<br>Air</b>',
                display: '13"',
                storage: 256,
                ram: 8,
                price: '<b>400</b> €',
            ),
            title: 'Pārdodu mazlietotu Macbook Air.',
        ));

        self::assertSame('Apple', $listing->parsedFields['brand']);
        self::assertSame('Air', $listing->parsedFields['model']);
        self::assertSame(400, $listing->price);
    }
}
