<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/app.php',
        __DIR__ . '/deploy.php',
    ])
    ->withPhpSets(php85: true);
