<?php

declare(strict_types=1);

namespace App\Domain;

use function md5;
use function str_replace;

final readonly class ListingRevisionCandidate
{
    public function __construct(
        public Listing $listing,
        public string $contentHash,
    ) {
    }

    public static function contentHash(string $content): string
    {
        return md5(str_replace("\n", '', $content));
    }
}
