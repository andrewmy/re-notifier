<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Banknote;

use App\Domain\ApartmentCriteria;
use App\Domain\Category;
use App\Domain\LaptopCriteria;
use App\Domain\ListingRevisionCandidate;
use App\Domain\ListingRevisionSourceFailed;
use App\Domain\WatchProfile;
use App\Infrastructure\Banknote\BanknoteInventoryRevisionSource;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class BanknoteInventoryRevisionSourceTest extends TestCase
{
    private const string SOURCE_URL = 'https://banknote.example.test/inventory/laptops/normalized.json';

    public function testSupportsBanknoteInventoryJson(): void
    {
        $source = new BanknoteInventoryRevisionSource(self::client(new Response(body: '{}')));

        self::assertTrue($source->supports('https://inventory.example.test/inventory/laptops/normalized.json'));
        self::assertTrue($source->supports(self::SOURCE_URL));
        self::assertFalse($source->supports('https://www.ss.lv/lv/electronics/computers/noutbooks/sell/rss/'));
    }

    public function testParsesLaptopInventoryRows(): void
    {
        $source = new BanknoteInventoryRevisionSource(self::client(new Response(body: json_encode([
            'inventory' => [
                [
                    'article' => 12345,
                    'id' => 67890,
                    'title' => 'Apple MacBook Air 13 M4',
                    'price' => 899.0,
                    'cpu' => 'Apple M4',
                    'ram' => '16GB',
                    'storage' => '512GB SSD',
                    'gpu' => '10-core GPU',
                    'defect' => '',
                    'city' => 'Rīga',
                    'local_address' => 'Brīvības iela',
                    'url' => 'https://veikals.banknote.lv/lv/products/apple-macbook-air-67890',
                    'images' => ['https://veikals.banknote.lv/storage/example.jpg'],
                    'timestamp' => '2026-06-06T12:00:00+00:00',
                ],
            ],
        ], JSON_THROW_ON_ERROR))));

        $candidates = $source->candidates(self::profile(), self::SOURCE_URL);

        self::assertCount(1, $candidates);
        self::assertSame('https://veikals.banknote.lv/lv/products/apple-macbook-air-67890', $candidates[0]->listing->url);
        self::assertSame(899, $candidates[0]->listing->price);
        self::assertSame('', $candidates[0]->listing->parsedFields['brand']);
        self::assertSame(16, $candidates[0]->listing->parsedFields['ramGb']);
        self::assertSame(512, $candidates[0]->listing->parsedFields['storageGb']);
        self::assertSame(0, $candidates[0]->listing->parsedFields['displayInches']);
        self::assertStringContainsString('cpu: Apple M4', $candidates[0]->listing->description);
        self::assertStringContainsString('gpu: 10-core GPU', $candidates[0]->listing->description);
        self::assertStringContainsString('address: Brīvības iela', $candidates[0]->listing->description);
        self::assertSame('https://veikals.banknote.lv/storage/example.jpg', $candidates[0]->listing->imageUrl);
        self::assertSame(ListingRevisionCandidate::contentHash(json_encode([
            'id' => 67890,
            'article' => 12345,
            'title' => 'Apple MacBook Air 13 M4',
            'price' => 899.0,
            'cpu' => 'Apple M4',
            'ram' => '16GB',
            'storage' => '512GB SSD',
            'gpu' => '10-core GPU',
            'defect' => '',
            'url' => 'https://veikals.banknote.lv/lv/products/apple-macbook-air-67890',
        ], JSON_THROW_ON_ERROR)), $candidates[0]->contentHash);
    }

    public function testNonLaptopProfilesReturnNoCandidates(): void
    {
        $source  = new BanknoteInventoryRevisionSource(self::client(new Response(body: '{}')));
        $profile = new WatchProfile(
            id: 'apartments',
            category: Category::Apartment,
            sourceUrls: [self::SOURCE_URL],
            criteria: new ApartmentCriteria(minRooms: 1, minSpace: 1, maxPrice: 1),
        );

        self::assertSame([], $source->candidates($profile, self::SOURCE_URL));
    }

    public function testParsesFallbackValues(): void
    {
        $source = new BanknoteInventoryRevisionSource(self::client(new Response(body: json_encode([
            'inventory' => [
                [
                    'title' => 'Apple MacBook Pro',
                    'price' => '950',
                    'storage' => '',
                    'url' => 'https://veikals.banknote.lv/lv/products/apple-macbook-pro',
                    'images' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR))));

        $candidates = $source->candidates(self::profile(), self::SOURCE_URL);

        self::assertCount(1, $candidates);
        self::assertSame(950, $candidates[0]->listing->price);
        self::assertSame(0, $candidates[0]->listing->parsedFields['ramGb']);
        self::assertSame(0, $candidates[0]->listing->parsedFields['storageGb']);
        self::assertNull($candidates[0]->listing->imageUrl);
    }

    public function testInvalidJsonFailsAsSourceFailure(): void
    {
        $source = new BanknoteInventoryRevisionSource(self::client(new Response(body: 'not-json')));

        $this->expectException(ListingRevisionSourceFailed::class);
        $this->expectExceptionMessage('Could not parse Banknote inventory');

        $source->candidates(self::profile(), self::SOURCE_URL);
    }

    public function testMissingInventoryFailsAsSourceFailure(): void
    {
        $source = new BanknoteInventoryRevisionSource(self::client(new Response(body: '{}')));

        $this->expectException(ListingRevisionSourceFailed::class);
        $this->expectExceptionMessage('Could not parse Banknote inventory');

        $source->candidates(self::profile(), self::SOURCE_URL);
    }

    private static function profile(): WatchProfile
    {
        return new WatchProfile(
            id: 'apple-laptops',
            category: Category::Laptop,
            sourceUrls: [self::SOURCE_URL],
            criteria: new LaptopCriteria(maxPrice: 1000),
        );
    }

    private static function client(Response $response): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
    }
}
