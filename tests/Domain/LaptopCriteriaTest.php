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
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

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

    public function testLaptopProfileIncludesSearchDescription(): void
    {
        $profile = self::profileWith(new LaptopCriteria(
            maxPrice: 1000,
            minRamGb: 16,
            minStorageGb: 512,
            titleIncludesAny: ['M4', '10-core GPU', '512GB SSD'],
        ));

        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 512,
            price: 899,
            title: 'Apple MacBook Air',
            description: "cpu: Apple M4\nram: 16GB\nstorage: 512GB SSD\ngpu: 10-core GPU",
        );

        self::assertTrue($profile->matches($listing));
    }

    public function testLaptopProfileMatchesParsedBrandBeforeTitle(): void
    {
        $profile = self::profileWith(new LaptopCriteria(brands: ['Apple']));

        $listing = self::laptopListing(
            brand: 'Lenovo',
            ram: 16,
            storage: 512,
            price: 899,
            title: 'Apple-like Windows laptop',
        );

        self::assertFalse($profile->matches($listing));
    }

    public function testLaptopProfileMatchesBrandAgainstTitleWhenParsedBrandIsEmpty(): void
    {
        $profile = self::profileWith(new LaptopCriteria(brands: ['Apple']));

        $listing = self::laptopListing(
            brand: '',
            ram: 16,
            storage: 512,
            price: 899,
            title: 'Apple MacBook Air 13 M4',
            description: 'description',
        );

        self::assertTrue($profile->matches($listing));
    }

    public function testLaptopProfileExcludesSearchDescription(): void
    {
        $profile = self::profileWith(new LaptopCriteria(titleExcludesAny: ['defekts']));

        $listing = self::laptopListing(
            brand: 'Apple',
            ram: 16,
            storage: 512,
            price: 899,
            title: 'Apple MacBook Air 13 M4',
            description: 'defect: Ir defekts',
        );

        self::assertFalse($profile->matches($listing));
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
                brands: ['Apple'],
            ),
        );
    }

    private static function profileWith(LaptopCriteria $criteria): WatchProfile
    {
        return new WatchProfile(
            id: 'test-laptops',
            category: Category::Laptop,
            sourceUrls: ['https://www.ss.lv/lv/electronics/computers/noutbooks/sell/rss/'],
            criteria: $criteria,
        );
    }

    private static function laptopListing(
        string $brand,
        int $ram,
        int $storage,
        int $price,
        string $title,
        string|null $description = null,
    ): LaptopListing {
        if ($description !== null) {
            return new LaptopListing(
                id: new Ulid(),
                url: 'https://www.ss.lv/msg/lv/electronics/computers/noutbooks/fxlge.html',
                description: $description,
                imageUrl: null,
                publishedAt: CarbonImmutable::parse('2026-06-05 10:48:59'),
                storedAt: CarbonImmutable::parse('2026-06-05 10:48:59'),
                price: $price,
                brand: $brand,
                model: 'Air',
                displayInches: 13,
                storageGb: $storage,
                ramGb: $ram,
                title: $title,
            );
        }

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
