<?php

declare(strict_types=1);

namespace App\Domain;

final class HouseCriteria implements Criteria
{
    public function __construct(
        public readonly int $minSpace,
        public readonly int $maxPrice,
        public readonly int $minLandArea = 0,
    ) {
    }

    public Category $category { get => Category::House; }

    public function matches(Listing $listing): bool
    {
        if ($listing->category !== Category::House) {
            return false;
        }

        $landArea = (int) ($listing->parsedFields['landArea'] ?? 0);

        return $listing->parsedFields['space'] >= $this->minSpace
            && ($listing->price <= $this->maxPrice || $listing->price === 0)
            && ($this->minLandArea === 0 || $landArea >= $this->minLandArea);
    }
}
