#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Infrastructure\DbalAdRepository;
use App\Ui\Cli\Update;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

$app = new Application();

$app->addCommand(
    new Update(
        $_ENV['TG_URI'],
        $_ENV['RSS_URL'],
        new DbalAdRepository($_ENV['DB_DSN']),
        new Logger(
            'main',
            [new StreamHandler($_ENV['LOG_DESTINATION'])],
        ),
    ),
);

$app->run();
