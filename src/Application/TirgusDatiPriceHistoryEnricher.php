<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Category;
use App\Domain\Listing;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

use function array_keys;
use function array_map;
use function implode;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class TirgusDatiPriceHistoryEnricher implements ListingEnricher
{
    public function __construct(
        private Client $client,
        private LoggerInterface|null $logger = null,
    ) {
    }

    public function enrich(Listing $listing): EnrichmentData|null
    {
        if (! self::supportsCategory($listing->category)) {
            return null;
        }

        $token = $this->fetchToken();
        if ($token === null) {
            return null;
        }

        return $this->fetchHistory($listing->url, $token);
    }

    private static function supportsCategory(Category $category): bool
    {
        return $category === Category::Apartment || $category === Category::House;
    }

    private function fetchToken(): string|null
    {
        try {
            $response = json_decode(
                (string) $this->client->get('https://api.tirgusdati.lv/api/user/me')->getBody(),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );

            Assert::isArray($response);
            Assert::keyExists($response, 'key');

            return (string) $response['key'];
        } catch (GuzzleException | JsonException | InvalidArgumentException $exception) {
            $this->logger?->error('Could not fetch TirgusDati token: ' . $exception->getMessage());

            return null;
        }
    }

    private function fetchHistory(string $url, string $token): EnrichmentData|null
    {
        $payload    = null;
        $statusCode = null;

        try {
            $response   = $this->client->post('https://api.tirgusdati.lv/api/listings/history/search', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => ['url' => $url],
            ]);
            $statusCode = $response->getStatusCode();
            $payload    = json_decode(
                (string) $response->getBody(),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );

            Assert::isArray($payload);
            Assert::keyExists($payload, 'id');
            Assert::keyExists($payload, 'timeline');
            Assert::isArray($payload['timeline']);
            Assert::keyExists($payload['timeline'], 'price_min');
            Assert::keyExists($payload['timeline'], 'price_max');
            Assert::keyExists($payload['timeline'], 'first');

            return new EnrichmentData(
                tdId: (string) $payload['id'],
                priceMin: (int) $payload['timeline']['price_min'],
                priceMax: (int) $payload['timeline']['price_max'],
                firstSeenAt: CarbonImmutable::createFromTimestamp((string) $payload['timeline']['first']),
            );
        } catch (GuzzleException | JsonException | InvalidArgumentException $exception) {
            $message = 'Could not fetch TirgusDati history for ' . $url . ': ' . $exception->getMessage();
            if ($statusCode !== null) {
                $message .= '; status=' . $statusCode;
            }

            if (is_array($payload)) {
                $message .= '; response_keys=' . implode(',', array_map('strval', array_keys($payload)));

                if (isset($payload['timeline']) && is_array($payload['timeline'])) {
                    $message .= '; timeline_keys=' . implode(',', array_map('strval', array_keys($payload['timeline'])));
                }
            }

            $this->logger?->error($message);

            return null;
        }
    }
}
