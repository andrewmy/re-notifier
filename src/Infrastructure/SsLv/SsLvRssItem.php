<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

final readonly class SsLvRssItem
{
    public function __construct(
        public string $publishedAt,
        public string $url,
        public string $description,
        public string $title = '',
    ) {
    }
}
