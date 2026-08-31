<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\TirgusDatiListingMatcher;
use App\Application\TirgusDatiPriceHistoryEnricher;
use App\Domain\ApartmentListing;
use App\Domain\LaptopListing;
use App\Tests\Support\Spy\SpyListingRepository;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Ulid;

final class TirgusDatiPriceHistoryEnricherTest extends TestCase
{
    public function testEnrichesApartmentListingWithPriceHistory(): void
    {
        $enricher = self::enricher([
            self::historyResponse(),
        ]);

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNotNull($result);
        self::assertSame(
            'https://tirgusdati.lv/vesture?q=https%3A%2F%2Fwww.ss.lv%2Fmsg%2Fru%2Freal-estate%2Fflats%2Friga%2Fcentre%2Fexample.html',
            $result->historyUrl,
        );
        self::assertSame(200000, $result->priceMin);
        self::assertSame(280000, $result->priceMax);
        self::assertSame('2023-11-14', $result->firstSeenAt->format('Y-m-d'));
    }

    public function testReturnsNullOnHistoryFetchFailure(): void
    {
        $enricher = self::enricher([
            new Response(status: 500),
        ]);

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNull($result);
    }

    public function testLogsHistoryPageContextWhenResponseShapeIsUnexpected(): void
    {
        $handler  = new TestHandler();
        $enricher = self::enricher([
            new Response(body: '<html><head><meta charset="utf-8"><title>Lapa nav atrasta — Tirgus Dati</title></head><body></body></html>'),
        ], new Logger('test', [$handler]));

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNull($result);
        self::assertCount(1, $handler->getRecords());
        self::assertTrue($handler->hasErrorThatContains($listing->url));
        self::assertTrue($handler->hasErrorThatContains('status=200'));
        self::assertTrue($handler->hasErrorThatContains('title=Lapa nav atrasta — Tirgus Dati'));
    }

    public function testReturnsNullAndLogsWhenHistoryPageIsEmpty(): void
    {
        $handler  = new TestHandler();
        $enricher = self::enricher([
            new Response(body: ''),
        ], new Logger('test', [$handler]));

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNull($result);
        self::assertTrue($handler->hasErrorThatContains($listing->url));
        self::assertTrue($handler->hasErrorThatContains('empty response'));
    }

    public function testReturnsNullAndLogsWhenHistoryDateIsInvalid(): void
    {
        $handler  = new TestHandler();
        $enricher = self::enricher([
            self::historyResponse('31.02.2026 12:27'),
        ], new Logger('test', [$handler]));

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNull($result);
        self::assertTrue($handler->hasErrorThatContains($listing->url));
        self::assertTrue($handler->hasErrorThatContains('invalid history date'));
    }

    public function testSkipsNonRealEstateListings(): void
    {
        $enricher = self::enricher([]);

        $result = $enricher->enrich(new LaptopListing(
            id: new Ulid(),
            url: 'https://www.ss.lv/msg/lv/electronics/computers/tablets/bccoih.html',
            description: 'test',
            imageUrl: null,
            publishedAt: CarbonImmutable::now(),
            storedAt: CarbonImmutable::now(),
            price: 500,
            brand: 'Apple',
            model: 'iPad',
            displayInches: 13,
            storageGb: 256,
            ramGb: 8,
            title: 'iPad Pro',
        ));

        self::assertNull($result);
    }

    public function testIncludesPreviousHistoryUrlsForSameProperty(): void
    {
        $previousSsLvUrl = 'https://www.ss.lv/msg/lv/real-estate/flats/riga/centre/bfpxb.html';
        $enricher        = self::enricher(
            [self::historyResponse()],
            repository: new SpyListingRepository(previousUrls: [$previousSsLvUrl]),
        );

        $result = $enricher->enrich(self::apartmentListing());

        self::assertNotNull($result);
        self::assertSame(['https://tirgusdati.lv/vesture?q=https%3A%2F%2Fwww.ss.lv%2Fmsg%2Flv%2Freal-estate%2Fflats%2Friga%2Fcentre%2Fbfpxb.html'], $result->previousHistoryUrls);
        self::assertFalse($result->historyMismatch);
    }

    public function testFlagsHistoryMismatchWhenPageDescribesDifferentListing(): void
    {
        $enricher = self::enricher([
            self::historyResponse(address: 'Valdemāra 39, Rīga', rooms: '4', area: '149 m²'),
        ]);

        $result = $enricher->enrich(self::apartmentListing(street: 'Čaka 133', rooms: 2, space: 40));

        self::assertNotNull($result);
        self::assertTrue($result->historyMismatch);
    }

    /** @param list<callable|Response> $queue */
    private static function enricher(
        array $queue,
        LoggerInterface|null $logger = null,
        SpyListingRepository|null $repository = null,
    ): TirgusDatiPriceHistoryEnricher {
        $handler = HandlerStack::create(new MockHandler($queue));
        $client  = new Client(['handler' => $handler]);

        return new TirgusDatiPriceHistoryEnricher(
            $client,
            $repository ?? new SpyListingRepository(),
            new TirgusDatiListingMatcher(),
            $logger,
        );
    }

    private static function historyResponse(
        string $firstSeenAt = '14.11.2023 22:13',
        string $address = 'Brivibas, Rīga',
        string $rooms = '4',
        string $area = '90 m²',
    ): Response {
        return new Response(
            body: <<<HTML
<!doctype html>
<html lang="lv">
<head><title>Dzīvoklis — cenu vēsture — Tirgus Dati</title></head>
<body>
<article class="listing">
    <div class="listing-head"><h1>Dzīvoklis pārdošanai</h1></div>
    <p class="muted">{$address}</p>
    <dl class="spec-list">
        <div><dt>Istabas</dt><dd>{$rooms}</dd></div>
        <div><dt>Platība</dt><dd>{$area}</dd></div>
    </dl>
</article>
<table class="history">
    <tbody>
        <tr><td>15.11.2023 12:27</td><td>200 000 EUR</td><td>-80 000 EUR</td><td></td></tr>
        <tr><td>{$firstSeenAt}</td><td>280 000 EUR</td><td>—</td><td>Sludinājums pievienots</td></tr>
    </tbody>
</table>
</body>
</html>
HTML,
        );
    }

    private static function apartmentListing(
        string $street = 'Brivibas',
        int $rooms = 4,
        int $space = 90,
    ): ApartmentListing {
        return new ApartmentListing(
            id: new Ulid(),
            url: 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html',
            description: 'test',
            imageUrl: null,
            publishedAt: CarbonImmutable::now(),
            storedAt: CarbonImmutable::now(),
            price: 250000,
            rooms: $rooms,
            space: $space,
            street: $street,
        );
    }
}
