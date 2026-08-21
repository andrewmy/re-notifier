<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Category;
use App\Domain\Listing;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\XPath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

use function max;
use function min;
use function preg_replace;
use function rawurlencode;
use function trim;

use const Dom\HTML_NO_DEFAULT_NS;
use const LIBXML_NOERROR;

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

        return $this->fetchHistory($listing->url);
    }

    private static function supportsCategory(Category $category): bool
    {
        return $category === Category::Apartment || $category === Category::House;
    }

    private function fetchHistory(string $url): EnrichmentData|null
    {
        $historyUrl    = 'https://tirgusdati.lv/vesture?q=' . rawurlencode($url);
        $statusCode    = null;
        $responseTitle = null;

        try {
            $response   = $this->client->get($historyUrl);
            $statusCode = $response->getStatusCode();
            $html       = (string) $response->getBody();
            Assert::stringNotEmpty($html, 'TirgusDati returned empty response');

            $document = HTMLDocument::createFromString($html, LIBXML_NOERROR | HTML_NO_DEFAULT_NS);
            $xpath    = new XPath($document);

            $title         = $xpath->query('//title')->item(0);
            $responseTitle = $title === null ? '' : trim($title->textContent ?? '');
            $rows          = $xpath->query(
                "//table[contains(concat(' ', normalize-space(@class), ' '), ' history ')]/tbody/tr",
            );
            Assert::greaterThan($rows->length, 0, 'TirgusDati response contains no history rows');

            $prices = [];
            $dates  = [];

            foreach ($rows as $row) {
                Assert::isInstanceOf($row, Element::class);

                $cells = $xpath->query('./td', $row);
                Assert::greaterThanEq($cells->length, 2, 'TirgusDati history row contains fewer than two cells');

                $dateCell  = $cells->item(0);
                $priceCell = $cells->item(1);

                Assert::isInstanceOf($dateCell, Element::class);
                Assert::isInstanceOf($priceCell, Element::class);

                $dateText  = trim($dateCell->textContent ?? '');
                $priceText = trim($priceCell->textContent ?? '');
                $price     = preg_replace('/\D+/', '', $priceText);

                Assert::stringNotEmpty($dateText);
                Assert::stringNotEmpty($price);

                $date = CarbonImmutable::createFromFormat('!d.m.Y H:i', $dateText, 'Europe/Riga');
                Assert::isInstanceOf($date, CarbonImmutable::class);
                Assert::same(
                    $date->format('d.m.Y H:i'),
                    $dateText,
                    'TirgusDati response contains invalid history date',
                );

                $dates[]  = $date;
                $prices[] = (int) $price;
            }

            Assert::notEmpty($dates);

            return new EnrichmentData(
                historyUrl: $historyUrl,
                priceMin: min($prices),
                priceMax: max($prices),
                firstSeenAt: min($dates),
            );
        } catch (GuzzleException | InvalidFormatException | InvalidArgumentException $exception) {
            $message = 'Could not fetch TirgusDati history for ' . $url . ': ' . $exception->getMessage();
            if ($statusCode !== null) {
                $message .= '; status=' . $statusCode;
            }

            if ($responseTitle !== null && $responseTitle !== '') {
                $message .= '; title=' . $responseTitle;
            }

            $this->logger?->error($message);

            return null;
        }
    }
}
