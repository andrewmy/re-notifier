<?php

declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;

final readonly class EnrichmentData
{
    /** @param list<string> $previousHistoryUrls */
    public function __construct(
        public string $historyUrl,
        public int $priceMin,
        public int $priceMax,
        public DateTimeImmutable $firstSeenAt,
        public array $previousHistoryUrls = [],
        public bool $historyMismatch = false,
    ) {
    }
}
