<?php

declare(strict_types=1);

use App\Domain\ApartmentCriteria;
use App\Domain\Category;
use App\Domain\HouseCriteria;
use App\Domain\LaptopCriteria;
use App\Domain\WatchProfile;

return [
    new WatchProfile(
        id: 'example-apartments',
        category: Category::Apartment,
        sourceUrls: ['https://www.ss.lv/lv/real-estate/flats/riga/all/sell/rss/'],
        criteria: new ApartmentCriteria(minRooms: 4, minSpace: 85, maxPrice: 260_000),
    ),
    new WatchProfile(
        id: 'example-houses',
        category: Category::House,
        sourceUrls: ['https://www.ss.lv/lv/real-estate/homes-summer-residences/riga/all/sell/rss/'],
        criteria: new HouseCriteria(minSpace: 100, maxPrice: 260_000),
    ),
    new WatchProfile(
        id: 'example-laptops',
        category: Category::Laptop,
        sourceUrls: [
            'https://www.ss.lv/lv/electronics/computers/noutbooks/sell/rss/',
            'https://banknote.example.test/inventory/laptops/normalized.json',
        ],
        criteria: new LaptopCriteria(
            maxPrice: 1000,
            minRamGb: 16,
            minStorageGb: 512,
            brands: ['Apple'],
            titleIncludesAny: ['M4', 'M3', 'M2', 'SSD'],
            titleExcludesAny: ['2019', '2020', 'defekts', 'bojāts'],
        ),
    ),
];
