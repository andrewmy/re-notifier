<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

interface Listing
{
    public Ulid $id { get; }

    public string $url { get; }

    public string $description { get; }

    public string|null $imageUrl { get; }

    public DateTimeImmutable $publishedAt { get; }

    public DateTimeImmutable $storedAt { get; }

    public int $price { get; }

    public Category $category { get; }

    /** @var array<string, int|string> */
    public array $parsedFields { get; }
}
