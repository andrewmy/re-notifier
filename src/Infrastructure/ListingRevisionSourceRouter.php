<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\ListingRevisionCandidate;
use App\Domain\ListingRevisionSource;
use App\Domain\ListingRevisionSourceFailed;
use App\Domain\WatchProfile;

final readonly class ListingRevisionSourceRouter implements ListingRevisionSource
{
    /** @param list<SelectableListingRevisionSource> $sources */
    public function __construct(private array $sources)
    {
    }

    /** @return list<ListingRevisionCandidate> */
    public function candidates(WatchProfile $watchProfile, string $sourceUrl): array
    {
        foreach ($this->sources as $source) {
            if ($source->supports($sourceUrl)) {
                return $source->candidates($watchProfile, $sourceUrl);
            }
        }

        throw new ListingRevisionSourceFailed('No listing revision source supports ' . $sourceUrl);
    }
}
