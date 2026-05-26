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
    ): string {
        return <<<HTML
Улица: <b>{$street}</b><br/>
К.: <b>{$rooms}</b><br/>
м²: <b>{$space}</b><br/>
Цена: <b>{$price}</b>
HTML;
    }
}
