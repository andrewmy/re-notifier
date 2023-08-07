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
        $obj->rooms            = (int) preg_replace('/.*К.: <b>(\d*)<\/b>.*/', '$1', $singleLineDescription);
        $obj->space            = (int) preg_replace('/.*m2: <b>(\d*)<\/b>.*/', '$1', $singleLineDescription);
        $obj->price            = (int) str_replace(
            ',',
            '',
            trim(preg_replace('/.*Цена: <b>([\d,]*)\s*€<\/b>.*/', '$1', $singleLineDescription)),
        );
        $street                = preg_replace('/.*Улица: <b>(.*)<\/b><br\/>К.*/', '$1', $singleLineDescription);
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
}
