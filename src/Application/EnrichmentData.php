<?php

declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;

final readonly class EnrichmentData
{
    public function __construct(
        public string $tdId,
        public int $priceMin,
        public int $priceMax,
        public DateTimeImmutable $firstSeenAt,
    ) {
    }
}
