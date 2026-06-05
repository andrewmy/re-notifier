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
<a href="https://www.ss.lv/msg/lv/real-estate/flats/riga/centre/example.html"><img align=right border=0 src="{$imageUrl}" width="174" height="130" alt=""></a>

HTML;

        return <<<HTML
{$image}
Iela: <b>{$street}</b><br/>
Ist.: <b>{$rooms}</b><br/>
m²: <b>{$space}</b><br/>
Cena: <b>{$price}</b>
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

    public static function laptop(
        string $brand = 'Apple',
        string $model = 'Air',
        string $display = '13"',
        int $storage = 256,
        int $ram = 16,
        string $price = '899 €',
        string|null $imageUrl = null,
    ): string {
        $image = $imageUrl === null ? '' : <<<HTML
<a href="https://www.ss.lv/msg/lv/electronics/computers/noutbooks/example.html"><img align=right border=0 src="{$imageUrl}" width="174" height="130" alt=""></a>

HTML;

        return <<<HTML
{$image}
Marka: <b>{$brand}</b><br/>
Modelis: <b>{$model}</b><br/>
Displejs: <b>{$display}</b><br/>
HDD: <b>{$storage}</b><br/>
RAM: <b>{$ram}</b><br/>
Cena: <b>{$price}</b>
HTML;
    }
}
