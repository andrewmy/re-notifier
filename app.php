#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Ui\Cli\Update;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

$app = new Application();

$app->add(
    new Update(
        $_ENV['TG_URI'],
        $_ENV['RSS_URL'],
        $_ENV['DB_DSN'],
        new Logger(
            'main',
            //[new StreamHandler('php://stdout')],
            [new StreamHandler('file://var/log.log')],
        ),
    ),
);

$app->run();
