<?php

declare(strict_types=1);

namespace App\Domain;

final class ApartmentCriteria implements Criteria
{
    public function __construct(
        public readonly int $minRooms,
        public readonly int $minSpace,
        public readonly int $maxPrice,
    ) {
    }

    public Category $category { get => Category::Apartment; }

    public function matches(Listing $listing): bool
    {
        if ($listing->category !== Category::Apartment) {
            return false;
        }

        return $listing->parsedFields['rooms'] >= $this->minRooms
            && $listing->parsedFields['space'] >= $this->minSpace
            && ($listing->price <= $this->maxPrice || $listing->price === 0);
    }
}
