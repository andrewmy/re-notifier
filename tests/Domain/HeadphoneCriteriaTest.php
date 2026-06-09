<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Category;
use App\Domain\HeadphoneCriteria;
use App\Domain\HeadphoneListing;
use App\Domain\LaptopListing;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class HeadphoneCriteriaTest extends TestCase
{
    public function testRejectsNonHeadphoneListing(): void
    {
        $criteria = new HeadphoneCriteria(modelIncludesAny: ['hd660s2']);

        self::assertSame(Category::Headphones, $criteria->category);

        $listing = new LaptopListing(
            id: new Ulid(),
            url: 'https://www.ss.lv/msg/lv/electronics/computers/noutbooks/example.html',
            description: 'Sennheiser HD 660S2',
            imageUrl: null,
            publishedAt: CarbonImmutable::parse('2026-06-08 10:00:00'),
            storedAt: CarbonImmutable::parse('2026-06-08 10:00:00'),
            price: 250,
            brand: 'Sennheiser',
            model: 'HD 660S2',
            displayInches: 0,
            storageGb: 0,
            ramGb: 0,
            title: 'Sennheiser HD 660S2',
        );

        self::assertFalse($criteria->matches($listing));
    }

    public function testHeadphoneListingExposesParsedFields(): void
    {
        $listing = self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 660S2',
        );

        self::assertSame(Category::Headphones, $listing->category);
        self::assertSame('Sennheiser', $listing->parsedFields['brand']);
        self::assertSame('lietota', $listing->parsedFields['condition']);
        self::assertSame('Sennheiser HD 660S2', $listing->parsedFields['title']);
    }

    public function testMatchesMessyHd660S2Spellings(): void
    {
        $criteria = new HeadphoneCriteria(modelIncludesAny: ['hd660s2']);

        self::assertTrue($criteria->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 660S 2 labā stāvoklī',
        )));

        self::assertTrue($criteria->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Pārdodu hd-660s2',
        )));
    }

    public function testMatchesMessyFiioFt1ProSpellings(): void
    {
        $criteria = new HeadphoneCriteria(modelIncludesAny: ['fiioft1pro', 'ft1pro']);

        self::assertTrue($criteria->matches(self::headphoneListing(
            brand: 'FiiO',
            condition: 'lietota',
            title: 'FiiO FT1 Pro',
        )));

        self::assertTrue($criteria->matches(self::headphoneListing(
            brand: '',
            condition: 'lietota',
            title: 'FT 1-PRO planāri',
        )));
    }

    public function testRejectsDifferentModel(): void
    {
        $criteria = new HeadphoneCriteria(modelIncludesAny: ['hd660s2']);

        self::assertFalse($criteria->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 650',
        )));
    }

    public function testRejectsExcludedTermFromDescription(): void
    {
        $criteria = new HeadphoneCriteria(
            modelIncludesAny: ['hd660s2'],
            titleExcludesAny: ['defekts', 'bojāts', 'replika'],
        );

        self::assertFalse($criteria->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 660S2',
            description: 'Ir defekts vienā pusē',
        )));
    }

    public function testPriceCapIsOptionalAndEnforcedWhenConfigured(): void
    {
        self::assertTrue((new HeadphoneCriteria(modelIncludesAny: ['hd660s2']))->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 660S2',
            price: 999,
        )));

        self::assertFalse((new HeadphoneCriteria(maxPrice: 300, modelIncludesAny: ['hd660s2']))->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 660S2',
            price: 301,
        )));

        self::assertTrue((new HeadphoneCriteria(maxPrice: 300, modelIncludesAny: ['hd660s2']))->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 660S2',
            price: 0,
        )));
    }

    public function testOptionalBrandAndConditionFilters(): void
    {
        $criteria = new HeadphoneCriteria(
            modelIncludesAny: ['hd660s2'],
            brands: ['Sennheiser'],
            conditions: ['lietota'],
        );

        self::assertTrue($criteria->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'lietota',
            title: 'Sennheiser HD 660S2',
        )));

        self::assertFalse($criteria->matches(self::headphoneListing(
            brand: 'Sony',
            condition: 'lietota',
            title: 'Sennheiser HD 660S2 comparison text',
        )));

        self::assertFalse($criteria->matches(self::headphoneListing(
            brand: 'Sennheiser',
            condition: 'jaun.',
            title: 'Sennheiser HD 660S2',
        )));
    }

    private static function headphoneListing(
        string $brand,
        string $condition,
        string $title,
        int $price = 250,
        string $description = '',
    ): HeadphoneListing {
        return new HeadphoneListing(
            id: new Ulid(),
            url: 'https://www.ss.lv/msg/lv/electronics/audio-video-dvd-sat/audio/headphones/example.html',
            description: $description === '' ? $title : $description,
            imageUrl: null,
            publishedAt: CarbonImmutable::parse('2026-06-08 10:00:00'),
            storedAt: CarbonImmutable::parse('2026-06-08 10:00:00'),
            price: $price,
            brand: $brand,
            condition: $condition,
            title: $title,
        );
    }
}
