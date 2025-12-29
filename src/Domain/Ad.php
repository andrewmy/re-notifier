<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

use function preg_match;
use function preg_replace;
use function str_replace;
use function trim;

final class Ad
{
    public readonly Ulid $id;
    public readonly DateTimeImmutable $updatedAt;
    public readonly DateTimeImmutable $publishedAt;
    public readonly string $url;
    public readonly int $rooms;
    public readonly int $space;
    public readonly int $price;
    public readonly string $street;
    public readonly string $description;

    public readonly string $tdId;
    public readonly int $priceMin;
    public readonly int $priceMax;
    public readonly DateTimeImmutable $firstSeenAt;

    private function __construct()
    {
        $this->id        = new Ulid();
        $this->updatedAt = CarbonImmutable::now();
    }

    public static function fromData(
        string $publishedAt,
        string $url,
        string $description,
    ): self {
        $obj                   = new self();
        $obj->publishedAt      = CarbonImmutable::createFromTimeString($publishedAt);
        $obj->url              = $url;
        $singleLineDescription = str_replace("\n", '', $description);
        $obj->rooms            = (int) preg_replace('/.*К.: (<b>)+(\d*)(<\/b>)+.*/u', '$2', $singleLineDescription);

        $space = (int) preg_replace('/.*m2: (<b>)+(\d*)(<\/b>)+.*/', '$2', $singleLineDescription);
        if ($space === 0) {
            $space = (int) preg_replace('/.*м²: (<b>)+(\d*)(<\/b>)+.*/u', '$2', $singleLineDescription);
        }

        $obj->space = $space;

        $obj->price = (int) str_replace(
            ',',
            '',
            trim(preg_replace('/.*Цена: (<b>)+([\d,]*)\s*€(<\/b>)+.*/u', '$2', $singleLineDescription)),
        );
        $street     = preg_replace('/.*Улица: (<b>)+(.*)(<\/b>)+<br\/>К.*/u', '$2', $singleLineDescription);
        if (preg_match('/.*[<=].*/', $street)) {
            $street = 'n/a';
        }

        $obj->street      = $street;
        $obj->description = $description;

        return $obj;
    }

    public function matches(): bool
    {
        return $this->rooms >= 4
            && $this->space >= 85
            && $this->price <= 260000;
    }

    public function addHistory(string $tdId, int $priceMin, int $priceMax, DateTimeImmutable $firstSeenAt): void
    {
        $this->tdId        = $tdId;
        $this->priceMin    = $priceMin;
        $this->priceMax    = $priceMax;
        $this->firstSeenAt = $firstSeenAt;
    }
}
