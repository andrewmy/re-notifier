<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\TirgusDatiListingMatcher;
use App\Domain\ApartmentListing;
use Carbon\CarbonImmutable;
use Dom\HTMLDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

use const Dom\HTML_NO_DEFAULT_NS;
use const LIBXML_NOERROR;

final class TirgusDatiListingMatcherTest extends TestCase
{
    public function testMatchingAddressRoomsAndAreaIsNotMismatch(): void
    {
        $document = self::document(address: 'Valdemāra 39, Rīga', rooms: '4', area: '149 m²');

        self::assertFalse((new TirgusDatiListingMatcher())->historyBelongsToDifferentListing(
            self::apartmentListing(street: 'Valdemāra 39', rooms: 4, space: 149),
            $document,
        ));
    }

    public function testTwoDifferentIdentityFieldsProduceMismatch(): void
    {
        $document = self::document(address: 'Valdemāra 39, Rīga', rooms: '4', area: '149 m²');

        self::assertTrue((new TirgusDatiListingMatcher())->historyBelongsToDifferentListing(
            self::apartmentListing(street: 'Čaka 133', rooms: 2, space: 40),
            $document,
        ));
    }

    public function testIncompleteTirgusDatiMetadataDoesNotClaimMismatch(): void
    {
        $document = HTMLDocument::createFromString(
            '<html><body><table class="history"></table></body></html>',
            LIBXML_NOERROR | HTML_NO_DEFAULT_NS,
        );

        self::assertFalse((new TirgusDatiListingMatcher())->historyBelongsToDifferentListing(
            self::apartmentListing(street: 'Čaka 133', rooms: 2, space: 40),
            $document,
        ));
    }

    private static function document(string $address, string $rooms, string $area): HTMLDocument
    {
        return HTMLDocument::createFromString(<<<HTML
<!doctype html>
<html lang="lv">
<body>
<article class="listing">
    <div class="listing-head"><h1>Dzīvoklis pārdošanai</h1></div>
    <p class="muted">{$address}</p>
    <dl class="spec-list">
        <div><dt>Istabas</dt><dd>{$rooms}</dd></div>
        <div><dt>Platība</dt><dd>{$area}</dd></div>
    </dl>
    <table class="history"><tbody><tr><td>19.01.2026 12:27</td><td>229 900 EUR</td><td>—</td><td>Sludinājums pievienots</td></tr></tbody></table>
</article>
</body>
</html>
HTML, LIBXML_NOERROR | HTML_NO_DEFAULT_NS);
    }

    private static function apartmentListing(string $street, int $rooms, int $space): ApartmentListing
    {
        return new ApartmentListing(
            id: new Ulid(),
            url: 'https://www.ss.lv/msg/lv/real-estate/flats/riga/centre/felxi.html',
            description: 'test',
            imageUrl: null,
            publishedAt: CarbonImmutable::now(),
            storedAt: CarbonImmutable::now(),
            price: 229900,
            rooms: $rooms,
            space: $space,
            street: $street,
        );
    }
}
