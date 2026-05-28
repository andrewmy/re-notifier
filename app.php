#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\TelegramNotifier;
use App\Infrastructure\DbalAdRepository;
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
assert(is_string($_ENV['RSS_URL']));
assert(is_string($_ENV['DB_DSN']));
assert(is_string($_ENV['LOG_DESTINATION']));

$app         = new Application();
$logger      = new Logger(
    'main',
    [new StreamHandler($_ENV['LOG_DESTINATION'])],
);
$httpClient  = new Client();
$httpFactory = new HttpFactory();

$app->addCommand(
    new Update(
        $_ENV['RSS_URL'],
        new DbalAdRepository($_ENV['DB_DSN']),
        new TelegramNotifier(
            $_ENV['TG_URI'],
            $httpClient,
            $httpFactory,
            $httpFactory,
            $logger,
        ),
        $logger,
    ),
);

$app->run();
