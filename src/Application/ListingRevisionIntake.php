<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\ListingRepository;
use App\Domain\ListingRevisionCandidate;
use App\Domain\ListingRevisionSource;
use App\Domain\WatchProfile;
use Psr\Log\LoggerInterface;

final readonly class ListingRevisionIntake
{
    public function __construct(
        private ListingRevisionSource $source,
        private ListingRepository $listingRepository,
        private LoggerInterface $logger,
    ) {
    }

    /** @return list<ListingRevisionCandidate> */
    public function pendingRevisions(WatchProfile $watchProfile): array
    {
        $revisions = [];

        foreach ($this->source->candidates($watchProfile) as $candidate) {
            $listing = $candidate->listing;

            if (! $watchProfile->matches($listing)) {
                $this->logger->debug('Listing does not match', [
                    'url' => $listing->url,
                    'profile' => $watchProfile->id,
                    'price' => $listing->price,
                    'parsedFields' => $listing->parsedFields,
                ]);

                continue;
            }

            if ($this->listingRepository->isSeen($watchProfile->id, $listing->url, $candidate->contentHash)) {
                $this->logger->debug('Listing revision already seen', ['url' => $listing->url, 'profile' => $watchProfile->id]);

                continue;
            }

            $revisions[] = new ListingRevisionCandidate(
                listing: $listing,
                contentHash: $candidate->contentHash,
            );
        }

        return $revisions;
    }
}
