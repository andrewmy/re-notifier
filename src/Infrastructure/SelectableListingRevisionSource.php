<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\ListingRevisionSource;

interface SelectableListingRevisionSource extends ListingRevisionSource
{
    public function supports(string $sourceUrl): bool;
}
