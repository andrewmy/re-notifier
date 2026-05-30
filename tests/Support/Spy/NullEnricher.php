<?php

declare(strict_types=1);

namespace App\Tests\Support\Spy;

use App\Application\EnrichmentData;
use App\Application\ListingEnricher;
use App\Domain\Listing;

final class NullEnricher implements ListingEnricher
{
    public function enrich(Listing $listing): EnrichmentData|null
    {
        return null;
    }
}
