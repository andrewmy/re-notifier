<?php

declare(strict_types=1);

namespace App\Domain;

use function str_replace;

final class WatchProfile
{
    public readonly string $hashtag;

    /** @param non-empty-list<string> $sourceUrls */
    public function __construct(
        public readonly string $id,
        public readonly Category $category,
        public readonly array $sourceUrls,
        public readonly Criteria $criteria,
    ) {
        $this->hashtag = '#' . str_replace('-', '_', $this->id);
    }

    public function matches(Listing $listing): bool
    {
        return $this->criteria->matches($listing);
    }
}
