<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use App\Domain\Listing;

interface SsLvParser
{
    public function parse(SsLvRssItem $item): Listing;
}
