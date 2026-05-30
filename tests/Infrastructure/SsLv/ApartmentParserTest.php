<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

use App\Domain\Category;
use App\Domain\Listing;
use App\Infrastructure\SsLv\ApartmentParser;
use App\Infrastructure\SsLv\SsLvRssItem;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

final class ApartmentParserTest extends TestCase
{
    public function testParsesApartmentListing(): void
    {
        $listing = $this->parse(SsLvDescription::apartment(
            street: 'Brivibas',
            rooms: 4,
            space: 90,
            price: '250,000 €',
        ));

        self::assertSame('https://www.ss.lv/msg/lv/real-estate/flats/riga/centre/example.html', $listing->url);
        self::assertSame(250000, $listing->price);
        self::assertSame(Category::Apartment, $listing->category);
        self::assertArrayHasKey('rooms', $listing->parsedFields);
        self::assertSame(4, $listing->parsedFields['rooms']);
        self::assertSame(90, $listing->parsedFields['space']);
        self::assertSame('Brivibas', $listing->parsedFields['street']);
        self::assertNull($listing->imageUrl);
    }

    public function testParsesImageUrl(): void
    {
        $listing = $this->parse(SsLvDescription::apartment(
            imageUrl: 'https://i.ss.com/gallery/8/1503/375702/75140261.t.jpg',
        ));

        self::assertSame(
            'https://i.ss.com/gallery/8/1503/375702/75140261.t.jpg',
            $listing->imageUrl,
        );
    }

    public function testParsesNestedBoldTags(): void
    {
        $listing = $this->parse(<<<'HTML'
Iela: <b><b>Ģertrūdes 9</b></b><br/>
Ist.: <b><b>4</b></b><br/>
m²: <b><b>100</b></b><br/>
Cena: <b><b>330,000</b>  €</b>
HTML);

        self::assertSame('Ģertrūdes 9', $listing->parsedFields['street']);
        self::assertSame(4, $listing->parsedFields['rooms']);
        self::assertSame(100, $listing->parsedFields['space']);
        self::assertSame(330000, $listing->price);
    }

    public function testParsesStreetWithoutBrTagNoiseFromBoldContent(): void
    {
        $listing = $this->parse(<<<'HTML'
<a href="https://www.ss.lv/msg/lv/real-estate/flats/riga/centre/bekolk.html"><img align=right border=0 src="https://i.ss.lv/gallery/8/1504/375869/75173717.t.jpg" width="174" height="130" alt=""></a>
		 Pagasts: <b><b>centrs<br>Brīvības 52</b></b><br/>Iela: <b><b>Brīvības 52 52<br>centrs<br>Brīvības 52</b></b><br/>Ist.: <b><b>5</b></b><br/>m²: <b><b>133</b></b><br/>Stāvs: <b><b>4/4</b></b><br/>Sērija: <b><b>P. kara</b></b><br/>: <b><b>7.14</b> €</b><br/>Cena: <b><b>950</b>  €/mēn.</b><br/><br/><b><a href="https://www.ss.lv/msg/lv/real-estate/flats/riga/centre/bekolk.html">Apskatīt sludinājumu</a></b><br/><br/>
		 
HTML);

        self::assertSame('Brīvības 52', $listing->parsedFields['street']);
        self::assertSame(5, $listing->parsedFields['rooms']);
        self::assertSame(133, $listing->parsedFields['space']);
        self::assertSame(950, $listing->price);
    }

    public function testMalformedStreetFallsBackToNotAvailable(): void
    {
        $listing = $this->parse(SsLvDescription::apartment(street: '<broken'));

        self::assertSame('n/a', $listing->parsedFields['street']);
    }

    public function testZeroPriceWhenUnparsable(): void
    {
        $listing = $this->parse(SsLvDescription::apartment(price: 'pērk'));

        self::assertSame(0, $listing->price);
    }

    private function parse(string $description, string $url = 'https://www.ss.lv/msg/lv/real-estate/flats/riga/centre/example.html'): Listing
    {
        return (new ApartmentParser())->parse(new SsLvRssItem(
            publishedAt: 'Thu, 28 May 2026 16:38:34 +0300',
            url: $url,
            description: $description,
        ));
    }
}
