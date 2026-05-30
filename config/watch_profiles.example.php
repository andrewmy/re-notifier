<?php

declare(strict_types=1);

use App\Domain\ApartmentCriteria;
use App\Domain\Category;
use App\Domain\HouseCriteria;
use App\Domain\WatchProfile;

return [
    new WatchProfile(
        id: 'example-apartments',
        category: Category::Apartment,
        rssUrl: 'https://www.ss.lv/ru/real-estate/flats/riga/all/sell/rss/',
        criteria: new ApartmentCriteria(minRooms: 4, minSpace: 85, maxPrice: 260_000),
    ),
    new WatchProfile(
        id: 'example-houses',
        category: Category::House,
        rssUrl: 'https://www.ss.lv/lv/real-estate/homes-summer-residences/riga/all/sell/rss/',
        criteria: new HouseCriteria(minSpace: 100, maxPrice: 260_000),
    ),
];
