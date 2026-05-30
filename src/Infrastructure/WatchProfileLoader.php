<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\WatchProfile;
use RuntimeException;
use Webmozart\Assert\Assert;

use function array_values;
use function file_exists;
use function gettype;
use function is_array;

final readonly class WatchProfileLoader
{
    /** @return list<WatchProfile> */
    public static function load(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException(
                'Watch profiles config not found: ' . $path
                . '. Copy config/watch_profiles.example.php to config/watch_profiles.local.php and adjust.',
            );
        }

        $result = require $path;

        if (! is_array($result)) {
            throw new RuntimeException(
                'Watch profiles config must return an array of WatchProfile instances, got ' . gettype($result),
            );
        }

        Assert::allIsInstanceOf($result, WatchProfile::class);

        return array_values($result);
    }
}
