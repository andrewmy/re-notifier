<?php

declare(strict_types=1);

namespace App\Tests\Support\Spy;

use App\Domain\Listing;
use App\Domain\ListingRepository;

final class SpyListingRepository implements ListingRepository
{
    public bool $saved = false;

    /** @param list<string> $previousUrls */
    public function __construct(
        private readonly bool $seen = false,
        private readonly array $previousUrls = [],
    ) {
    }

    public function isSeen(string $watchProfileId, string $url, string $contentHash): bool
    {
        return $this->seen;
    }

    public function save(Listing $listing, string $watchProfileId, string $contentHash): void
    {
        $this->saved = true;
    }

    /** @return list<array<string, mixed>> */
    public function findRawDescriptionsByUrl(string $url): array
    {
        return [];
    }

    /** @return list<string> */
    public function findPreviousUrlsForSameProperty(Listing $listing): array
    {
        return $this->previousUrls;
    }
}
