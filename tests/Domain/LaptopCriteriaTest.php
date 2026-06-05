<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Category;
use App\Domain\LaptopCriteria;
use App\Domain\LaptopListing;
use App\Domain\WatchProfile;
use App\Infrastructure\SsLv\LaptopParser;
use App\Infrastructure\SsLv\SsLvRssItem;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

use function number_format;

final class LaptopCriteriaTest extends TestCase
{
    public function testMatchesLaptopByStructuredFieldsAndTitle(): void
    {
        $profile = self::laptopProfile();
        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 512,
            price: 850,
            title: 'Pārdodu Apple Macbook Air M4',
        );

        self::assertTrue($profile->matches($listing));
    }

    public function testRejectsLaptopWithoutRequiredTitleKeyword(): void
    {
        $profile = self::laptopProfile();
        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 512,
            price: 850,
            title: 'Pārdodu Apple Macbook Air M2',
        );

        self::assertFalse($profile->matches($listing));
    }

    public function testRejectsLaptopWithExcludedTitleKeyword(): void
    {
        $profile = self::laptopProfile();
        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 512,
            price: 850,
            title: 'Pārdodu Apple Macbook Air M4 remontam',
        );

        self::assertFalse($profile->matches($listing));
    }

    public function testRejectsTooExpensiveLaptop(): void
    {
        $profile = self::laptopProfile();
        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 512,
            price: 901,
            title: 'Pārdodu Apple Macbook Air M4',
        );

        self::assertFalse($profile->matches($listing));
    }

    public function testMatchesAnyPriceWhenMaxPriceIsUnset(): void
    {
        $profile = self::profileWith(new LaptopCriteria(minRamGb: 16));
        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 512,
            price: 2_500,
            title: 'Pārdodu Apple Macbook Pro M4',
        );

        self::assertTrue($profile->matches($listing));
    }

    public function testRejectsUnlistedBrand(): void
    {
        $profile = self::laptopProfile();
        $listing = self::laptopListing(
            brand: 'Asus',
            ram: 16,
            storage: 512,
            price: 850,
            title: 'Pārdodu Asus Vivobook M4',
        );

        self::assertFalse($profile->matches($listing));
    }

    public function testMatchesWhenTitleAndBrandFiltersAreUnset(): void
    {
        $profile = self::profileWith(new LaptopCriteria(maxPrice: 900, minRamGb: 16));
        $listing = self::laptopListing(
            brand: 'Asus',
            ram: 16,
            storage: 256,
            price: 850,
            title: 'Pārdodu Asus Vivobook',
        );

        self::assertTrue($profile->matches($listing));
    }

    public function testMatchesTitleKeywordCaseInsensitively(): void
    {
        $profile = self::profileWith(new LaptopCriteria(maxPrice: 900, titleIncludesAny: ['macbook']));
        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 256,
            price: 850,
            title: 'Pārdodu Apple MacBook Air',
        );

        self::assertTrue($profile->matches($listing));
    }

    private static function laptopProfile(): WatchProfile
    {
        return self::profileWith(
            new LaptopCriteria(
                maxPrice: 900,
                minRamGb: 16,
                minStorageGb: 256,
                titleIncludesAny: ['M4', 'M3'],
                titleExcludesAny: ['remontam'],
                allowedBrands: ['Apple'],
            ),
        );
    }

    private static function profileWith(LaptopCriteria $criteria): WatchProfile
    {
        return new WatchProfile(
            id: 'test-laptops',
            category: Category::Laptop,
            rssUrl: 'https://www.ss.lv/lv/electronics/computers/noutbooks/sell/rss/',
            criteria: $criteria,
        );
    }

    private static function laptopListing(
        string $brand,
        int $ram,
        int $storage,
        int $price,
        string $title,
    ): LaptopListing {
        $result = (new LaptopParser())->parse(new SsLvRssItem(
            publishedAt: 'Fri, 05 Jun 2026 10:48:59 +0300',
            url: 'https://www.ss.lv/msg/lv/electronics/computers/noutbooks/fxlge.html',
            description: SsLvDescription::laptop(
                brand: $brand,
                model: 'Air',
                display: '13"',
                storage: $storage,
                ram: $ram,
                price: number_format($price, thousands_separator: ' ') . ' €',
            ),
            title: $title,
        ));

        self::assertInstanceOf(LaptopListing::class, $result);

        return $result;
    }
}
