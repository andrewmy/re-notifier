<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

use App\Domain\Category;
use App\Infrastructure\SsLv\SsLvPropertyFingerprint;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

final class SsLvPropertyFingerprintTest extends TestCase
{
    public function testSameApartmentFieldsProduceEqualFingerprints(): void
    {
        $first  = SsLvPropertyFingerprint::fromDescription(
            Category::Apartment,
            SsLvDescription::apartment(street: 'Valdemāra 39', rooms: 4, space: 149, floor: '7/7'),
        );
        $second = SsLvPropertyFingerprint::fromDescription(
            Category::Apartment,
            SsLvDescription::apartment(street: '  Valdemāra   39 ', rooms: 4, space: 149, floor: '7/7'),
        );

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($second, $first);
    }

    public function testDifferentFloorDoesNotMatch(): void
    {
        $first  = SsLvPropertyFingerprint::fromDescription(
            Category::Apartment,
            SsLvDescription::apartment(street: 'Valdemāra 39', rooms: 4, space: 149, floor: '7/7'),
        );
        $second = SsLvPropertyFingerprint::fromDescription(
            Category::Apartment,
            SsLvDescription::apartment(street: 'Valdemāra 39', rooms: 4, space: 149, floor: '6/7'),
        );

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($second, $first);
    }

    public function testIncompleteApartmentCannotBeFingerprinted(): void
    {
        self::assertNull(SsLvPropertyFingerprint::fromDescription(
            Category::Apartment,
            SsLvDescription::apartment(street: 'n/a', rooms: 4, space: 149, floor: '7/7'),
        ));
    }

    public function testSameHouseFieldsProduceEqualFingerprints(): void
    {
        $first  = SsLvPropertyFingerprint::fromDescription(
            Category::House,
            SsLvDescription::house(street: 'Babītes 10', rooms: 5, space: 180, landArea: '1 200', floors: 2),
        );
        $second = SsLvPropertyFingerprint::fromDescription(
            Category::House,
            SsLvDescription::house(street: 'Babītes 10', rooms: 5, space: 180, landArea: '1 200', floors: 2),
        );

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($second, $first);
    }
}
