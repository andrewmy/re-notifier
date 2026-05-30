<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

final class ApartmentListing implements Listing
{
    public readonly Category $category;

    /** @var array<string, int|string> */
    public readonly array $parsedFields;

    public function __construct(
        public readonly Ulid $id,
        public readonly string $url,
        public readonly string $description,
        public readonly string|null $imageUrl,
        public readonly DateTimeImmutable $publishedAt,
        public readonly DateTimeImmutable $storedAt,
        public readonly int $price,
        public readonly int $rooms,
        public readonly int $space,
        public readonly string $street,
    ) {
        $this->category     = Category::Apartment;
        $this->parsedFields = [
            'rooms' => $this->rooms,
            'space' => $this->space,
            'street' => $this->street,
        ];
    }
}
