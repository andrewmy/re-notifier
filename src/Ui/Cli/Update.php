<?php

declare(strict_types=1);

namespace App\Ui\Cli;

use App\Application\ListingEnricher;
use App\Application\Notifier;
use App\Domain\Category;
use App\Domain\Listing;
use App\Domain\ListingRepository;
use App\Domain\WatchProfile;
use App\Infrastructure\SsLv\SsLvParser;
use App\Infrastructure\SsLv\SsLvRssItem;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

use function implode;
use function md5;
use function number_format;
use function sprintf;
use function str_replace;

use const LIBXML_NOCDATA;

final class Update extends Command
{
    /**
     * @param list<WatchProfile>        $watchProfiles
     * @param array<string, SsLvParser> $parsers
     */
    public function __construct(
        private readonly array $watchProfiles,
        private readonly array $parsers,
        private readonly ListingRepository $listingRepository,
        private readonly Notifier $notifier,
        private readonly ListingEnricher $enricher,
        private readonly LoggerInterface $logger,
        private readonly Client $rssClient,
    ) {
        parent::__construct('update');
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'dry-run',
            description: 'Do not send Telegram notifications and do not save revisions',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        foreach ($this->watchProfiles as $profile) {
            $parser = $this->parsers[$profile->category->value]
                ?? throw new InvalidArgumentException('No parser for category ' . $profile->category->value);

            try {
                $rssFeedBody = (string) $this->rssClient->get($profile->rssUrl)->getBody();
            } catch (GuzzleException $exception) {
                $this->logger->error('Could not fetch RSS feed: ' . $exception->getMessage());

                return 1;
            }

            try {
                $rssFeedXml = new SimpleXMLElement($rssFeedBody, LIBXML_NOCDATA);
            } catch (Throwable $exception) {
                $this->logger->error('Could not parse RSS feed: ' . $exception->getMessage());

                return 1;
            }

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
                $contentHash = md5(str_replace("\n", '', $description));

                $listing = $parser->parse(new SsLvRssItem(
                    publishedAt: (string) $item->pubDate,
                    url: $url,
                    description: $description,
                ));

                if (! $profile->matches($listing)) {
                    $this->logger->debug('Listing does not match', [
                        'url' => $listing->url,
                        'profile' => $profile->id,
                        'price' => $listing->price,
                        'parsedFields' => $listing->parsedFields,
                    ]);

                    continue;
                }

                if ($this->listingRepository->isSeen($profile->id, $url, $contentHash)) {
                    $this->logger->debug('Listing revision already seen', ['url' => $url, 'profile' => $profile->id]);

                    continue;
                }

                $message = $this->formatMessage($listing, $profile);

                $this->logger->info('Found matching listing', [
                    'url' => $listing->url,
                    'profile' => $profile->id,
                    'price' => $listing->price,
                    'parsedFields' => $listing->parsedFields,
                    'imageUrl' => $listing->imageUrl,
                ]);

                if ($dryRun) {
                    $this->logger->debug('Dry run: skipping send and save', ['message' => $message]);

                    continue;
                }

                $this->notifier->send($message, $listing->imageUrl);
                $this->listingRepository->save($listing, $profile->id, $contentHash);
            }
        }

        return 0;
    }

    private function formatMessage(Listing $listing, WatchProfile $profile): string
    {
        $enrichment = $this->enricher->enrich($listing);

        $lines = [
            $profile->hashtag,
            $listing->url,
        ];

        foreach ($listing->parsedFields as $label => $value) {
            if ($label === 'price' || $label === 'landAreaRaw' || ($listing->category === Category::House && $label === 'rooms')) {
                continue;
            }

            $strValue = (string) $value;

            if ($label === 'landArea') {
                $lines[] = 'land: ' . $strValue . ' m²';

                continue;
            }

            if ($label === 'space') {
                $lines[] = 'space: ' . $strValue . ' m²';

                continue;
            }

            $lines[] = $label . ': ' . $strValue;
        }

        $lines[] = '€: ' . number_format($listing->price, thousands_separator: ' ');

        if ($enrichment !== null) {
            $lines[] = sprintf(
                "€ min: %s\n€ max: %s\nFirst seen: %s\nhttps://tirgusdati.lv/app/listings/history/%s",
                number_format($enrichment->priceMin, thousands_separator: ' '),
                number_format($enrichment->priceMax, thousands_separator: ' '),
                $enrichment->firstSeenAt->format('Y-m-d'),
                $enrichment->tdId,
            );
        }

        return implode("\n", $lines);
    }
}
