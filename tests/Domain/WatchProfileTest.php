<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\ApartmentCriteria;
use App\Domain\ApartmentListing;
use App\Domain\Category;
use App\Domain\HouseCriteria;
use App\Domain\HouseListing;
use App\Domain\WatchProfile;
use App\Infrastructure\SsLv\ApartmentParser;
use App\Infrastructure\SsLv\HouseParser;
use App\Infrastructure\SsLv\SsLvRssItem;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

use function number_format;

final class WatchProfileTest extends TestCase
{
    public function testApartmentProfileMatchesMatchingApartment(): void
    {
        $profile = self::apartmentProfile();
        $listing = self::apartmentListing(rooms: 4, space: 90, price: 250000);

        self::assertTrue($profile->matches($listing));
    }

    public function testApartmentProfileRejectsTooFewRooms(): void
    {
        $profile = self::apartmentProfile();
        $listing = self::apartmentListing(rooms: 3, space: 90, price: 250000);

        self::assertFalse($profile->matches($listing));
    }

    public function testApartmentProfileRejectsTooSmallSpace(): void
    {
        $profile = self::apartmentProfile();
        $listing = self::apartmentListing(rooms: 4, space: 84, price: 250000);

        self::assertFalse($profile->matches($listing));
    }

    public function testApartmentProfileRejectsTooExpensive(): void
    {
        $profile = self::apartmentProfile();
        $listing = self::apartmentListing(rooms: 4, space: 90, price: 260001);

        self::assertFalse($profile->matches($listing));
    }

    public function testApartmentProfileAllowsZeroPrice(): void
    {
        $profile = self::apartmentProfile();
        $listing = self::apartmentListing(rooms: 4, space: 90, price: 0);

        self::assertTrue($profile->matches($listing));
    }

    public function testApartmentProfileRejectsHouseListing(): void
    {
        $profile = self::apartmentProfile();
        $listing = self::houseListing(rooms: 5, space: 120, price: 180000);

        self::assertFalse($profile->matches($listing));
    }

    public function testSameListingMatchesOneProfileAndFailsAnother(): void
    {
        $strict  = self::apartmentProfile();
        $lenient = new WatchProfile(
            id: 'riga-lenient-apartments',
            category: Category::Apartment,
            rssUrl: 'https://www.ss.lv/ru/real-estate/flats/riga/all/rss/',
            criteria: new ApartmentCriteria(minRooms: 2, minSpace: 50, maxPrice: 300000),
        );

        $small = self::apartmentListing(rooms: 3, space: 60, price: 200000);

        self::assertFalse($strict->matches($small));
        self::assertTrue($lenient->matches($small));
    }

    public function testHashtagDerivedFromId(): void
    {
        $profile = self::apartmentProfile();

        self::assertSame('#riga_family_apartments', $profile->hashtag);
    }

    public function testHouseProfileMatchesMatchingHouse(): void
    {
        $profile = self::houseProfile();
        $listing = self::houseListing(rooms: 5, space: 120, price: 180000);

        self::assertTrue($profile->matches($listing));
    }

    public function testHouseProfileRejectsTooExpensiveHouse(): void
    {
        $profile = self::houseProfile();
        $listing = self::houseListing(rooms: 5, space: 120, price: 300001);

        self::assertFalse($profile->matches($listing));
    }

    public function testHouseProfileAppliesConfiguredMinimumLandArea(): void
    {
        $profile = new WatchProfile(
            id: 'babites-large-land-houses',
            category: Category::House,
            rssUrl: 'https://www.ss.lv/lv/real-estate/homes-summer-residences/riga-region/babites-pag/rss/',
            criteria: new HouseCriteria(minSpace: 100, maxPrice: 300000, minLandArea: 1500),
        );

        self::assertFalse($profile->matches(self::houseListing(rooms: 5, space: 120, price: 180000, landArea: '1 200')));
        self::assertTrue($profile->matches(self::houseListing(rooms: 5, space: 120, price: 180000, landArea: '1 800')));
    }

    private static function apartmentProfile(): WatchProfile
    {
        return new WatchProfile(
            id: 'riga-family-apartments',
            category: Category::Apartment,
            rssUrl: 'https://www.ss.lv/ru/real-estate/flats/riga/all/rss/',
            criteria: new ApartmentCriteria(minRooms: 4, minSpace: 85, maxPrice: 260000),
        );
    }

    private static function houseProfile(): WatchProfile
    {
        return new WatchProfile(
            id: 'babites-family-houses',
            category: Category::House,
            rssUrl: 'https://www.ss.lv/lv/real-estate/homes-summer-residences/riga-region/babites-pag/rss/',
            criteria: new HouseCriteria(minSpace: 100, maxPrice: 300000),
        );
    }

    private static function apartmentListing(int $rooms = 4, int $space = 90, int $price = 250000): ApartmentListing
    {
        $parser = new ApartmentParser();
        $result = $parser->parse(new SsLvRssItem(
            publishedAt: 'Thu, 28 May 2026 16:38:34 +0300',
            url: 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html',
            description: SsLvDescription::apartment(rooms: $rooms, space: $space, price: self::formatPrice($price)),
        ));

        self::assertInstanceOf(ApartmentListing::class, $result);

        return $result;
    }

    private static function houseListing(
        int $rooms = 5,
        int $space = 120,
        int $price = 180000,
        string $landArea = '1 200',
    ): HouseListing {
        $parser = new HouseParser();
        $result = $parser->parse(new SsLvRssItem(
            publishedAt: 'Thu, 28 May 2026 16:38:34 +0300',
            url: 'https://www.ss.lv/msg/lv/real-estate/homes-summer-residences/riga-region/babites/example.html',
            description: SsLvDescription::house(rooms: $rooms, space: $space, landArea: $landArea, price: self::formatPrice($price)),
        ));

        self::assertInstanceOf(HouseListing::class, $result);

        return $result;
    }

    private static function formatPrice(int $price): string
    {
        if ($price === 0) {
            return 'куплю';
        }

        return number_format($price, thousands_separator: ' ') . ' €';
    }
}
