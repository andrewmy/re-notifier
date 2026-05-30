<?php

declare(strict_types=1);

namespace App\Domain;

interface ListingRepository
{
    public function isSeen(string $watchProfileId, string $url, string $contentHash): bool;

    public function save(Listing $listing, string $watchProfileId, string $contentHash): void;

    /** @return list<array<string, mixed>> */
    public function findRawDescriptionsByUrl(string $url): array;
}
