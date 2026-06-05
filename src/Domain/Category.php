<?php

declare(strict_types=1);

namespace App\Domain;

enum Category: string
{
    case Apartment = 'apartment';
    case House     = 'house';
    case Laptop    = 'laptop';
}
