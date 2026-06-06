<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\ApartmentCriteria;
use App\Domain\Category;
use App\Domain\LaptopCriteria;
use App\Domain\WatchProfile;
use App\Infrastructure\SsLv\ApartmentParser;
use App\Infrastructure\SsLv\HouseParser;
use App\Infrastructure\SsLv\LaptopParser;
use App\Infrastructure\SsLv\SsLvParser;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class SsLvFixtures
{
    public const string APARTMENT_URL = 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html';

    public static function apartmentProfile(): WatchProfile
    {
        return new WatchProfile(
            id: 'riga-family-apartments',
            category: Category::Apartment,
            sourceUrls: ['https://www.ss.lv/ru/real-estate/flats/riga/all/rss/'],
            criteria: new ApartmentCriteria(minRooms: 4, minSpace: 85, maxPrice: 260000),
        );
    }

    public static function laptopProfile(): WatchProfile
    {
        return new WatchProfile(
            id: 'apple-laptops',
            category: Category::Laptop,
            sourceUrls: ['https://www.ss.lv/lv/electronics/computers/noutbooks/sell/rss/'],
            criteria: new LaptopCriteria(maxPrice: 900, minRamGb: 16),
        );
    }

    /** @return array<string, SsLvParser> */
    public static function parsers(): array
    {
        return [
            Category::Apartment->value => new ApartmentParser(),
            Category::House->value => new HouseParser(),
            Category::Laptop->value => new LaptopParser(),
        ];
    }

    public static function rssClient(Response $response): Client
    {
        return new Client([
            'handler' => HandlerStack::create(new MockHandler([$response])),
        ]);
    }

    public static function rssFeed(string $description, string $url = self::APARTMENT_URL): Response
    {
        return new Response(
            body: <<<XML
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
<channel><title>Test</title>
<item>
    <title><![CDATA[Test listing title]]></title>
    <link>{$url}</link>
    <pubDate>Thu, 28 May 2026 16:38:34 +0300</pubDate>
    <description><![CDATA[{$description}]]></description>
</item>
</channel>
</rss>
XML,
        );
    }
}
