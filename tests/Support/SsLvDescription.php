<?php

declare(strict_types=1);

namespace App\Tests\Support;

final class SsLvDescription
{
    public static function apartment(
        string $street = 'Brivibas',
        int $rooms = 4,
        int $space = 90,
        string $price = '250,000 €',
        string|null $imageUrl = null,
    ): string {
        $image = $imageUrl === null ? '' : <<<HTML
<a href="https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html"><img align=right border=0 src="{$imageUrl}" width="174" height="130" alt=""></a>

HTML;

        return <<<HTML
{$image}
Улица: <b>{$street}</b><br/>
К.: <b>{$rooms}</b><br/>
м²: <b>{$space}</b><br/>
Цена: <b>{$price}</b>
HTML;
    }

    public static function house(
        string $street = 'Babites',
        int $rooms = 5,
        int $space = 120,
        string $landArea = '1 200',
        string $landUnit = 'm²',
        int $floors = 2,
        string $price = '180,000 €',
        string|null $imageUrl = null,
    ): string {
        $image = $imageUrl === null ? '' : <<<HTML
<a href="https://www.ss.lv/msg/lv/real-estate/homes-summer-residences/riga-region/babites/example.html"><img align=right border=0 src="{$imageUrl}" width="174" height="130" alt=""></a>

HTML;

        return <<<HTML
{$image}
Iela: <b>{$street}</b><br/>
m²: <b>{$space}</b><br/>
Stāvi: <b>{$floors}</b><br/>
Ist.: <b>{$rooms}</b><br/>
Zem. pl.: <b>{$landArea} {$landUnit}</b><br/>
Cena: <b>{$price}</b>
HTML;
    }
}
