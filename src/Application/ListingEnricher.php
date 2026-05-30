<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Listing;

interface ListingEnricher
{
    public function enrich(Listing $listing): EnrichmentData|null;
}
