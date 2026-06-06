<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

final class LaptopListing implements Listing
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
        public readonly string $brand,
        public readonly string $model,
        public readonly int $displayInches,
        public readonly int $storageGb,
        public readonly int $ramGb,
        public readonly string $title,
        public readonly string $cpu = '',
    ) {
        $parsedFields = [
            'brand' => $this->brand,
            'model' => $this->model,
        ];

        if ($this->cpu !== '') {
            $parsedFields['cpu'] = $this->cpu;
        }

        $this->category     = Category::Laptop;
        $this->parsedFields = $parsedFields + [
            'displayInches' => $this->displayInches,
            'storageGb' => $this->storageGb,
            'ramGb' => $this->ramGb,
            'title' => $this->title,
        ];
    }
}
