<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Ad;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

final class AdTest extends TestCase
{
    public function testMatchingApartmentMatches(): void
    {
        $ad = Ad::fromData(
            '2026-01-01 12:00:00',
            'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html',
            SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €'),
        );

        self::assertSame(4, $ad->rooms);
        self::assertSame(90, $ad->space);
        self::assertSame(250000, $ad->price);
        self::assertSame('Brivibas', $ad->street);
        self::assertTrue($ad->matches());
    }

    public function testTooFewRoomsDoesNotMatch(): void
    {
        $ad = Ad::fromData(
            '2026-01-01 12:00:00',
            'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/few-rooms.html',
            SsLvDescription::apartment(rooms: 3, space: 90, price: '250,000 €'),
        );

        self::assertFalse($ad->matches());
    }

    public function testTooSmallApartmentDoesNotMatch(): void
    {
        $ad = Ad::fromData(
            '2026-01-01 12:00:00',
            'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/small.html',
            SsLvDescription::apartment(rooms: 4, space: 84, price: '250,000 €'),
        );

        self::assertFalse($ad->matches());
    }

    public function testTooExpensiveApartmentDoesNotMatch(): void
    {
        $ad = Ad::fromData(
            '2026-01-01 12:00:00',
            'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/expensive.html',
            SsLvDescription::apartment(rooms: 4, space: 90, price: '260,001 €'),
        );

        self::assertFalse($ad->matches());
    }

    public function testBuyerPriceStillMatchesBecauseZeroPriceMeansParsingFailed(): void
    {
        $ad = Ad::fromData(
            '2026-01-01 12:00:00',
            'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/buyer.html',
            SsLvDescription::apartment(rooms: 4, space: 90, price: 'куплю'),
        );

        self::assertSame(0, $ad->price);
        self::assertTrue($ad->matches());
    }

    public function testMalformedStreetFallsBackToNotAvailable(): void
    {
        $ad = Ad::fromData(
            '2026-01-01 12:00:00',
            'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/malformed.html',
            SsLvDescription::apartment(street: '<broken', rooms: 4, space: 90, price: '250,000 €'),
        );

        self::assertSame('n/a', $ad->street);
    }
}
