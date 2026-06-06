<?php

declare(strict_types=1);

namespace App\Infrastructure\SsLv;

use App\Domain\ListingRevisionCandidate;
use App\Domain\ListingRevisionSourceFailed;
use App\Domain\WatchProfile;
use App\Infrastructure\SelectableListingRevisionSource;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Throwable;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

use function parse_url;

use const LIBXML_NOCDATA;
use const PHP_URL_HOST;

final readonly class SsLvListingRevisionSource implements SelectableListingRevisionSource
{
    /** @param array<string, SsLvParser> $parsers */
    public function __construct(
        private array $parsers,
        private LoggerInterface $logger,
        private Client $rssClient,
    ) {
    }

    public function supports(string $sourceUrl): bool
    {
        return parse_url($sourceUrl, PHP_URL_HOST) === 'www.ss.lv';
    }

    /** @return list<ListingRevisionCandidate> */
    public function candidates(WatchProfile $watchProfile, string $sourceUrl): array
    {
        $parser = $this->parsers[$watchProfile->category->value]
            ?? throw new InvalidArgumentException('No parser for category ' . $watchProfile->category->value);

        try {
            $rssFeedBody = (string) $this->rssClient->get($sourceUrl)->getBody();
        } catch (GuzzleException $exception) {
            throw new ListingRevisionSourceFailed('Could not fetch RSS feed: ' . $exception->getMessage(), previous: $exception);
        }

        try {
            $rssFeedXml = new SimpleXMLElement($rssFeedBody, LIBXML_NOCDATA);
        } catch (Throwable $exception) {
            throw new ListingRevisionSourceFailed('Could not parse RSS feed: ' . $exception->getMessage(), previous: $exception);
        }

        $candidates = [];

        foreach ($rssFeedXml->channel->item as $item) {
            try {
                Assert::propertyExists($item, 'link');
                Assert::propertyExists($item, 'pubDate');
                Assert::propertyExists($item, 'description');
            } catch (InvalidArgumentException $exception) {
                $this->logger->warning('Bad RSS item', ['message' => $exception->getMessage()]);

                continue;
            }

            $url         = (string) $item->link;
            $description = (string) $item->description;

            $candidates[] = ListingRevisionCandidate::fromListing(
                $parser->parse(new SsLvRssItem(
                    publishedAt: (string) $item->pubDate,
                    url: $url,
                    description: $description,
                    title: (string) ($item->title ?? ''),
                )),
            );
        }

        return $candidates;
    }
}
