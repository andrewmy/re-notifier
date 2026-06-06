#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\ListingRevisionIntake;
use App\Application\TelegramNotifier;
use App\Application\TirgusDatiPriceHistoryEnricher;
use App\Domain\Category;
use App\Infrastructure\Banknote\BanknoteInventoryRevisionSource;
use App\Infrastructure\DbalListingRepository;
use App\Infrastructure\ListingRevisionSourceRouter;
use App\Infrastructure\SsLv\ApartmentParser;
use App\Infrastructure\SsLv\HouseParser;
use App\Infrastructure\SsLv\LaptopParser;
use App\Infrastructure\SsLv\SsLvListingRevisionSource;
use App\Infrastructure\WatchProfileLoader;
use App\Ui\Cli\ListingRaw;
use App\Ui\Cli\Update;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

assert(is_string($_ENV['TG_URI']));
assert(is_string($_ENV['DB_DSN']));
assert(is_string($_ENV['LOG_DESTINATION']));

$app         = new Application();
$logger      = new Logger(
    'main',
    [new StreamHandler($_ENV['LOG_DESTINATION'])],
);
$httpClient  = new Client();
$httpFactory = new HttpFactory();

$watchProfiles  = WatchProfileLoader::load(__DIR__ . '/config/watch_profiles.local.php');
$listingRepo    = new DbalListingRepository($_ENV['DB_DSN']);
$revisionIntake = new ListingRevisionIntake(
    new ListingRevisionSourceRouter([
        new SsLvListingRevisionSource(
            [
                Category::Apartment->value => new ApartmentParser(),
                Category::House->value => new HouseParser(),
                Category::Laptop->value => new LaptopParser(),
            ],
            $logger,
            $httpClient,
        ),
        new BanknoteInventoryRevisionSource($httpClient),
    ]),
    $listingRepo,
    $logger,
);

$app->addCommand(
    new Update(
        $watchProfiles,
        $revisionIntake,
        $listingRepo,
        new TelegramNotifier(
            $_ENV['TG_URI'],
            $httpClient,
            $httpFactory,
            $httpFactory,
            $logger,
        ),
        new TirgusDatiPriceHistoryEnricher(new Client(['cookies' => true]), $logger),
        $logger,
    ),
);

$app->addCommand(
    new ListingRaw($listingRepo),
);

$app->run();
