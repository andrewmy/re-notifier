<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\TirgusDatiPriceHistoryEnricher;
use App\Domain\ApartmentListing;
use App\Domain\LaptopListing;
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

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class TirgusDatiPriceHistoryEnricherTest extends TestCase
{
    public function testEnrichesApartmentListingWithPriceHistory(): void
    {
        $enricher = self::enricher([
            self::meResponse(),
            self::historyResponse(),
        ]);

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNotNull($result);
        self::assertSame('td-123', $result->tdId);
        self::assertSame(200000, $result->priceMin);
        self::assertSame(280000, $result->priceMax);
    }

    public function testReturnsNullOnTokenFetchFailure(): void
    {
        $enricher = self::enricher([
            new Response(status: 500),
        ]);

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNull($result);
    }

    public function testReturnsNullOnHistoryFetchFailure(): void
    {
        $enricher = self::enricher([
            self::meResponse(),
            new Response(status: 500),
        ]);

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNull($result);
    }

    public function testLogsHistoryPayloadContextWhenResponseShapeIsUnexpected(): void
    {
        $handler  = new TestHandler();
        $enricher = self::enricher([
            self::meResponse(),
            new Response(body: json_encode([
                'timeline' => ['price_min' => 200000],
            ], JSON_THROW_ON_ERROR)),
        ], new Logger('test', [$handler]));

        $listing = self::apartmentListing();
        $result  = $enricher->enrich($listing);

        self::assertNull($result);
        self::assertCount(1, $handler->getRecords());
        self::assertTrue($handler->hasErrorThatContains($listing->url));
        self::assertTrue($handler->hasErrorThatContains('response_keys=timeline'));
        self::assertTrue($handler->hasErrorThatContains('timeline_keys=price_min'));
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

    /** @param list<callable|Response> $queue */
    private static function enricher(array $queue, LoggerInterface|null $logger = null): TirgusDatiPriceHistoryEnricher
    {
        $handler = HandlerStack::create(new MockHandler($queue));
        $client  = new Client(['handler' => $handler, 'cookies' => true]);

        return new TirgusDatiPriceHistoryEnricher($client, $logger);
    }

    private static function meResponse(): Response
    {
        return new Response(
            body: json_encode(['key' => 'test-token'], JSON_THROW_ON_ERROR),
        );
    }

    private static function historyResponse(): Response
    {
        return new Response(
            body: json_encode([
                'id' => 'td-123',
                'timeline' => [
                    'price_min' => 200000,
                    'price_max' => 280000,
                    'first' => 1700000000,
                ],
            ], JSON_THROW_ON_ERROR),
        );
    }

    private static function apartmentListing(): ApartmentListing
    {
        return new ApartmentListing(
            id: new Ulid(),
            url: 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html',
            description: 'test',
            imageUrl: null,
            publishedAt: CarbonImmutable::now(),
            storedAt: CarbonImmutable::now(),
            price: 250000,
            rooms: 4,
            space: 90,
            street: 'Brivibas',
        );
    }
}
