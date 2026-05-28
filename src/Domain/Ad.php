<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function trim;

final readonly class Ad
{
    public Ulid $id;
    public DateTimeImmutable $updatedAt;
    public DateTimeImmutable $publishedAt;
    public string $url;
    public int $rooms;
    public int $space;
    public int $price;
    public string $street;
    public string $description;

    public string $tdId;
    public int $priceMin;
    public int $priceMax;
    public DateTimeImmutable $firstSeenAt;

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
        $obj->rooms            = self::integerField($singleLineDescription, 'К.');

        $space = self::integerField($singleLineDescription, 'm2');
        if ($space === 0) {
            $space = self::integerField($singleLineDescription, 'м²');
        }

        $obj->space = $space;

        $obj->price = (int) preg_replace(
            '/\D/u',
            '',
            self::fieldText($singleLineDescription, 'Цена'),
        );
        $street     = self::fieldText($singleLineDescription, 'Улица');
        if ($street === '' || preg_match('/.*[<=].*/', $street)) {
            $street = 'n/a';
        }

        $obj->street      = $street;
        $obj->description = $description;

        return $obj;
    }

    private static function integerField(string $description, string $label): int
    {
        return (int) preg_replace(
            '/\D/u',
            '',
            self::fieldText($description, $label),
        );
    }

    private static function fieldText(string $description, string $label): string
    {
        if (! preg_match('/' . preg_quote($label, '/') . ':\s*(.*?)(?:<br\/>|$)/u', $description, $matches)) {
            return '';
        }

        return trim(str_replace(
            ',',
            '',
            strip_tags($matches[1]),
        ));
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
