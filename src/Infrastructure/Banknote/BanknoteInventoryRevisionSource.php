<?php

declare(strict_types=1);

namespace App\Infrastructure\Banknote;

use App\Domain\Category;
use App\Domain\LaptopListing;
use App\Domain\ListingRevisionCandidate;
use App\Domain\ListingRevisionSourceFailed;
use App\Domain\WatchProfile;
use App\Infrastructure\SelectableListingRevisionSource;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Symfony\Component\Uid\Ulid;

use function array_key_exists;
use function implode;
use function is_array;
use function is_numeric;
use function is_scalar;
use function json_decode;
use function json_encode;
use function preg_match;
use function str_contains;
use function str_replace;
use function strtoupper;
use function trim;

use const JSON_THROW_ON_ERROR;

final readonly class BanknoteInventoryRevisionSource implements SelectableListingRevisionSource
{
    public function __construct(private Client $httpClient)
    {
    }

    public function supports(string $sourceUrl): bool
    {
        return str_contains($sourceUrl, '/inventory/laptops/normalized.json');
    }

    /** @return list<ListingRevisionCandidate> */
    public function candidates(WatchProfile $watchProfile, string $sourceUrl): array
    {
        if ($watchProfile->category !== Category::Laptop) {
            return [];
        }

        try {
            $body = (string) $this->httpClient->get($sourceUrl)->getBody();
        } catch (GuzzleException $exception) {
            throw new ListingRevisionSourceFailed(
                'Could not fetch Banknote inventory: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        try {
            $payload = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ListingRevisionSourceFailed('Could not parse Banknote inventory', previous: $exception);
        }

        if (! is_array($payload) || ! isset($payload['inventory']) || ! is_array($payload['inventory'])) {
            throw new ListingRevisionSourceFailed('Could not parse Banknote inventory: missing inventory array');
        }

        $candidates = [];

        foreach ($payload['inventory'] as $row) {
            if (! is_array($row)) {
                throw new ListingRevisionSourceFailed('Could not parse Banknote inventory: invalid inventory row');
            }

            $title   = self::stringField($row, 'title');
            $ram     = self::stringField($row, 'ram');
            $storage = self::stringField($row, 'storage');

            $candidates[] = new ListingRevisionCandidate(
                listing: new LaptopListing(
                    id: new Ulid(),
                    url: self::stringField($row, 'url'),
                    description: self::description($row),
                    imageUrl: self::imageUrl($row),
                    publishedAt: self::timestamp($row),
                    storedAt: CarbonImmutable::now(),
                    price: self::numericField($row, 'price'),
                    brand: '',
                    model: $title,
                    displayInches: 0,
                    storageGb: self::storageGb($storage),
                    ramGb: self::integerFromText($ram),
                    title: $title,
                ),
                contentHash: ListingRevisionCandidate::contentHash(self::hashContent($row)),
            );
        }

        return $candidates;
    }

    /** @param array<mixed, mixed> $row */
    private static function description(array $row): string
    {
        return implode("\n", [
            self::stringField($row, 'title'),
            'article: ' . self::stringField($row, 'article'),
            'id: ' . self::stringField($row, 'id'),
            'cpu: ' . self::stringField($row, 'cpu'),
            'ram: ' . self::stringField($row, 'ram'),
            'storage: ' . self::stringField($row, 'storage'),
            'gpu: ' . self::stringField($row, 'gpu'),
            'defect: ' . self::stringField($row, 'defect'),
            'city: ' . self::stringField($row, 'city'),
            'address: ' . self::stringField($row, 'local_address'),
        ]);
    }

    /** @param array<mixed, mixed> $row */
    private static function hashContent(array $row): string
    {
        try {
            return json_encode([
                'id' => self::value($row, 'id'),
                'article' => self::value($row, 'article'),
                'title' => self::value($row, 'title'),
                'price' => self::value($row, 'price'),
                'cpu' => self::value($row, 'cpu'),
                'ram' => self::value($row, 'ram'),
                'storage' => self::value($row, 'storage'),
                'gpu' => self::value($row, 'gpu'),
                'defect' => self::value($row, 'defect'),
                'url' => self::value($row, 'url'),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ListingRevisionSourceFailed('Could not parse Banknote inventory row', previous: $exception);
        }
    }

    /** @param array<mixed, mixed> $row */
    private static function imageUrl(array $row): string|null
    {
        $images = self::value($row, 'images');

        if (! is_array($images) || ! isset($images[0]) || ! is_scalar($images[0])) {
            return null;
        }

        $imageUrl = trim((string) $images[0]);

        return $imageUrl === '' ? null : $imageUrl;
    }

    /** @param array<mixed, mixed> $row */
    private static function timestamp(array $row): CarbonImmutable
    {
        $timestamp = self::stringField($row, 'timestamp');

        if ($timestamp === '') {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::parse($timestamp);
    }

    private static function storageGb(string $storage): int
    {
        if (! preg_match('/(\d+(?:[,.]\d+)?)\s*(tb|gb)?/i', $storage, $matches)) {
            return 0;
        }

        $amount = (float) str_replace(',', '.', $matches[1]);
        $unit   = strtoupper($matches[2] ?? 'GB');

        return (int) ($unit === 'TB' ? $amount * 1024 : $amount);
    }

    private static function integerFromText(string $text): int
    {
        if (! preg_match('/\d+/', $text, $matches)) {
            return 0;
        }

        return (int) $matches[0];
    }

    /** @param array<mixed, mixed> $row */
    private static function stringField(array $row, string $field): string
    {
        $value = self::value($row, $field);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<mixed, mixed> $row */
    private static function numericField(array $row, string $field): int
    {
        $value = self::value($row, $field);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param array<mixed, mixed> $row */
    private static function value(array $row, string $field): mixed
    {
        return array_key_exists($field, $row) ? $row[$field] : '';
    }
}
