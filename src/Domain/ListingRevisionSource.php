<?php

declare(strict_types=1);

namespace App\Domain;

interface ListingRevisionSource
{
    /** @return list<ListingRevisionCandidate> */
    public function candidates(WatchProfile $watchProfile): array;
}
