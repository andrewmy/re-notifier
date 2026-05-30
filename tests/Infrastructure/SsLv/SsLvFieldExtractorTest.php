<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

use App\Infrastructure\SsLv\SsLvFieldExtractor;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

final class SsLvFieldExtractorTest extends TestCase
{
    public function testExtractsApartmentFieldsFromSingleLineDescription(): void
    {
        $plainText = SsLvFieldExtractor::toPlainText(
            SsLvDescription::apartment(street: 'Brivibas', rooms: 4, space: 90, price: '250,000 €'),
        );

        self::assertSame('Brivibas', SsLvFieldExtractor::fieldText($plainText, 'Iela'));
        self::assertSame(4, SsLvFieldExtractor::integerField($plainText, 'Ist.'));
        self::assertSame(90, SsLvFieldExtractor::integerField($plainText, 'm²'));
        self::assertSame(250000, SsLvFieldExtractor::integerField($plainText, 'Cena'));
    }

    public function testExtractsFirstImageUrl(): void
    {
        $description = SsLvDescription::apartment(
            imageUrl: 'https://i.ss.com/gallery/8/1503/375702/75140261.t.jpg',
        );

        self::assertSame(
            'https://i.ss.com/gallery/8/1503/375702/75140261.t.jpg',
            SsLvFieldExtractor::imageUrl($description),
        );
    }

    public function testReturnsNullWhenNoImage(): void
    {
        $description = SsLvDescription::apartment();

        self::assertNull(SsLvFieldExtractor::imageUrl($description));
    }

    public function testExtractsFieldsFromNestedBoldTags(): void
    {
        $plainText = SsLvFieldExtractor::toPlainText(<<<'HTML'
Iela: <b><b>Ģertrūdes 9</b></b><br/>
Ist.: <b><b>4</b></b><br/>
m²: <b><b>100</b></b><br/>
Cena: <b><b>330,000</b>  €</b>
HTML);

        self::assertSame('Ģertrūdes 9', SsLvFieldExtractor::fieldText($plainText, 'Iela'));
        self::assertSame(4, SsLvFieldExtractor::integerField($plainText, 'Ist.'));
        self::assertSame(100, SsLvFieldExtractor::integerField($plainText, 'm²'));
        self::assertSame(330000, SsLvFieldExtractor::integerField($plainText, 'Cena'));
    }

    public function testReturnsEmptyForMissingField(): void
    {
        $plainText = SsLvFieldExtractor::toPlainText(SsLvDescription::apartment());

        self::assertSame('', SsLvFieldExtractor::fieldText($plainText, 'Nonexistent'));
        self::assertSame(0, SsLvFieldExtractor::integerField($plainText, 'Nonexistent'));
    }

    public function testExtractsHouseFieldsWithDirectLabels(): void
    {
        $plainText = SsLvFieldExtractor::toPlainText(
            SsLvDescription::house(street: 'Babites', rooms: 5, space: 120, landArea: '1 200', landUnit: 'm²', floors: 2, price: '180,000 €'),
        );

        self::assertSame('Babites', SsLvFieldExtractor::fieldText($plainText, 'Iela'));
        self::assertSame(5, SsLvFieldExtractor::integerField($plainText, 'Ist.'));
        self::assertSame(120, SsLvFieldExtractor::integerField($plainText, 'm²'));
        self::assertSame(2, SsLvFieldExtractor::integerField($plainText, 'Stāvi'));
        self::assertSame(1200, SsLvFieldExtractor::integerField($plainText, 'Zem. pl.'));
        self::assertSame(180000, SsLvFieldExtractor::integerField($plainText, 'Cena'));
    }

    public function testHouseLandAreaInHectaresNormalizesToSquareMeters(): void
    {
        $plainText = SsLvFieldExtractor::toPlainText(
            SsLvDescription::house(landArea: '0.5', landUnit: 'ha.'),
        );

        self::assertSame('0.5 ha.', SsLvFieldExtractor::fieldText($plainText, 'Zem. pl.'));
    }
}
