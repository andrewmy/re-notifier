<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Listing;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

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
        $token = $this->fetchToken();
        if ($token === null) {
            return null;
        }

        return $this->fetchHistory($listing->url, $token);
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
        try {
            $response = json_decode(
                (string) $this->client->post('https://api.tirgusdati.lv/api/listings/history/search', [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'json' => ['url' => $url],
                ])->getBody(),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );

            Assert::isArray($response);
            Assert::keyExists($response, 'id');
            Assert::keyExists($response, 'timeline');
            Assert::isArray($response['timeline']);
            Assert::keyExists($response['timeline'], 'price_min');
            Assert::keyExists($response['timeline'], 'price_max');
            Assert::keyExists($response['timeline'], 'first');

            return new EnrichmentData(
                tdId: (string) $response['id'],
                priceMin: (int) $response['timeline']['price_min'],
                priceMax: (int) $response['timeline']['price_max'],
                firstSeenAt: CarbonImmutable::createFromTimestamp((string) $response['timeline']['first']),
            );
        } catch (GuzzleException | JsonException | InvalidArgumentException $exception) {
            $this->logger?->error('Could not fetch TirgusDati history: ' . $exception->getMessage());

            return null;
        }
    }
}
