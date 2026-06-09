<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

use App\Domain\Category;
use App\Domain\HeadphoneListing;
use App\Infrastructure\SsLv\HeadphoneParser;
use App\Infrastructure\SsLv\SsLvRssItem;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

final class HeadphoneParserTest extends TestCase
{
    public function testParsesHeadphoneListing(): void
    {
        $listing = (new HeadphoneParser())->parse(new SsLvRssItem(
            publishedAt: 'Mon, 08 Jun 2026 18:55:13 +0300',
            url: 'https://www.ss.lv/msg/lv/electronics/audio-video-dvd-sat/audio/headphones/bfkexe.html',
            description: SsLvDescription::headphones(
                brand: 'Sennheiser',
                condition: 'lietota',
                price: '250 €',
            ),
            title: 'Pārdodu Sennheiser HD 660S 2 austiņas',
        ));

        self::assertInstanceOf(HeadphoneListing::class, $listing);
        self::assertSame(Category::Headphones, $listing->category);
        self::assertSame(250, $listing->price);
        self::assertSame('Sennheiser', $listing->parsedFields['brand']);
        self::assertSame('lietota', $listing->parsedFields['condition']);
        self::assertSame('Pārdodu Sennheiser HD 660S 2 austiņas', $listing->parsedFields['title']);
        self::assertSame('https://i.ss.lv/gallery/8/1508/376828/75365434.t.jpg', $listing->imageUrl);
    }

    public function testParsesNestedBoldPriceBrandAndCondition(): void
    {
        $listing = (new HeadphoneParser())->parse(new SsLvRssItem(
            publishedAt: 'Mon, 08 Jun 2026 18:55:13 +0300',
            url: 'https://www.ss.lv/msg/lv/electronics/audio-video-dvd-sat/audio/headphones/bfkexe.html',
            description: SsLvDescription::headphones(
                brand: '<b>FiiO</b>',
                condition: '<b>jaun.</b>',
                price: '<b>199</b> €',
            ),
            title: 'Fiio FT1 Pro',
        ));

        self::assertSame(199, $listing->price);
        self::assertSame('FiiO', $listing->parsedFields['brand']);
        self::assertSame('jaun.', $listing->parsedFields['condition']);
    }
}
