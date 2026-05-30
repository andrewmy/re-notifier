<?php

declare(strict_types=1);

namespace App\Domain;

interface Criteria
{
    public Category $category { get; }

    public function matches(Listing $listing): bool;
}
