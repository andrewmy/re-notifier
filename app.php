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
use App\Infrastructure\SsLv\HeadphoneParser;
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

$tgUri          = $_ENV['TG_URI'];
$dbDsn          = $_ENV['DB_DSN'];
$logDestination = $_ENV['LOG_DESTINATION'];

assert(is_string($tgUri));
assert(is_string($dbDsn));
assert(is_string($logDestination));

$app         = new Application();
$logger      = new Logger(
    'main',
    [new StreamHandler($logDestination)],
);
$httpClient  = new Client();
$httpFactory = new HttpFactory();

$watchProfiles  = WatchProfileLoader::load(__DIR__ . '/config/watch_profiles.local.php');
$listingRepo    = new DbalListingRepository($dbDsn);
$revisionIntake = new ListingRevisionIntake(
    new ListingRevisionSourceRouter([
        new SsLvListingRevisionSource(
            [
                Category::Apartment->value => new ApartmentParser(),
                Category::House->value => new HouseParser(),
                Category::Laptop->value => new LaptopParser(),
                Category::Headphones->value => new HeadphoneParser(),
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
            $tgUri,
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
