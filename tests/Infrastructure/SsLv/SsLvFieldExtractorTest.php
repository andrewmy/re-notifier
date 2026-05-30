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

        self::assertSame('Brivibas', SsLvFieldExtractor::fieldText($plainText, 'Улица'));
        self::assertSame(4, SsLvFieldExtractor::integerField($plainText, 'К.'));
        self::assertSame(90, SsLvFieldExtractor::integerField($plainText, 'м²'));
        self::assertSame(250000, SsLvFieldExtractor::integerField($plainText, 'Цена'));
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
Улица: <b><b>Миера 5</b></b><br/>
К.: <b><b>4</b></b><br/>
м²: <b><b>100</b></b><br/>
Цена: <b><b>330,000</b>  €</b>
HTML);

        self::assertSame('Миера 5', SsLvFieldExtractor::fieldText($plainText, 'Улица'));
        self::assertSame(4, SsLvFieldExtractor::integerField($plainText, 'К.'));
        self::assertSame(100, SsLvFieldExtractor::integerField($plainText, 'м²'));
        self::assertSame(330000, SsLvFieldExtractor::integerField($plainText, 'Цена'));
    }

    public function testReturnsEmptyForMissingField(): void
    {
        $plainText = SsLvFieldExtractor::toPlainText(SsLvDescription::apartment());

        self::assertSame('', SsLvFieldExtractor::fieldText($plainText, 'Nonexistent'));
        self::assertSame(0, SsLvFieldExtractor::integerField($plainText, 'Nonexistent'));
    }

    public function testAliasedFieldTextTriesLabelsInOrder(): void
    {
        $apartment = SsLvFieldExtractor::toPlainText(SsLvDescription::apartment(street: 'Brivibas'));
        $house     = SsLvFieldExtractor::toPlainText(SsLvDescription::house(street: 'Babites'));

        self::assertSame('Brivibas', SsLvFieldExtractor::aliasedFieldText($apartment, 'Улица', 'Iela'));
        self::assertSame('Babites', SsLvFieldExtractor::aliasedFieldText($house, 'Улица', 'Iela'));
        self::assertSame('Babites', SsLvFieldExtractor::aliasedFieldText($house, 'Iela', 'Улица'));
    }

    public function testAliasedIntegerFieldTriesLabelsInOrder(): void
    {
        $apartment = SsLvFieldExtractor::toPlainText(SsLvDescription::apartment(rooms: 4));
        $house     = SsLvFieldExtractor::toPlainText(SsLvDescription::house(rooms: 5));

        self::assertSame(4, SsLvFieldExtractor::aliasedIntegerField($apartment, 'К.', 'Ist.'));
        self::assertSame(5, SsLvFieldExtractor::aliasedIntegerField($house, 'К.', 'Ist.'));
        self::assertSame(5, SsLvFieldExtractor::aliasedIntegerField($house, 'Ist.', 'К.'));
    }

    public function testAliasedFieldReturnsFirstNonEmptyMatch(): void
    {
        $plainText = SsLvFieldExtractor::toPlainText(SsLvDescription::apartment(rooms: 4));

        self::assertSame(4, SsLvFieldExtractor::aliasedIntegerField($plainText, 'К.', 'Ist.'));
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
