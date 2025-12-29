<?php

declare(strict_types=1);

namespace App\Ui\Cli;

use App\Domain\Ad;
use App\Domain\AdRepository;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

use function json_decode;
use function number_format;
use function sprintf;
use function urlencode;

use const JSON_THROW_ON_ERROR;
use const LIBXML_NOCDATA;

final class Update extends Command
{
    public function __construct(
        private readonly string $tgUri,
        private readonly string $rssUrl,
        private readonly AdRepository $adRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct('update');
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'no-tg',
            description: 'Do not post to Telegram',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $doPostToTg = ! (bool) $input->getOption('no-tg');

        $cookielessClient = new Client();
        $tdClient         = new Client(['cookies' => true]);

        try {
            $rssFeedBody = (string) $cookielessClient->get($this->rssUrl)->getBody();
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

        $tdToken = '';
        try {
            $tdResponse = json_decode(
                (string) $tdClient->get('https://api.tirgusdati.lv/api/user/me')->getBody(),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
            Assert::isArray($tdResponse);
            Assert::keyExists($tdResponse, 'key');
            $tdToken = (string) $tdResponse['key'];
        } catch (GuzzleException | JsonException | InvalidArgumentException $exception) {
            $this->logger->error('Could not fetch TirgusDati token: ' . $exception->getMessage());
        }

        foreach ($rssFeedXml->channel->item as $item) {
            try {
                Assert::propertyExists($item, 'link');
                Assert::propertyExists($item, 'pubDate');
                Assert::propertyExists($item, 'description');
            } catch (InvalidArgumentException $exception) {
                $this->logger->warning(
                    'Bad RSS item',
                    ['message' => $exception->getMessage()],
                );

                continue;
            }

            $url         = (string) $item->link;
            $description = (string) $item->description;

            if ($this->adRepository->exists($url, $description)) {
                $this->logger->debug('Ad already exists', ['url' => $url]);

                continue;
            }

            $ad = Ad::fromData(
                (string) $item->pubDate,
                $url,
                $description,
            );

            if (! $ad->matches()) {
                $this->logger->debug('Ad does not match', [
                    'rooms' => $ad->rooms,
                    'space' => $ad->space,
                    'price' => $ad->price,
                    'description' => $ad->rooms === 0 || $ad->space === 0 || $ad->price === 0 ? $ad->description : '-',
                ]);

                continue;
            }

            $this->adRepository->save($ad);

            try {
                $tdResponse = json_decode(
                    (string) $tdClient->post('https://api.tirgusdati.lv/api/listings/history/search', [
                        'headers' => ['Authorization' => 'Bearer ' . $tdToken],
                        'json' => ['url' => $ad->url],
                    ])->getBody(),
                    associative: true,
                    flags: JSON_THROW_ON_ERROR,
                );

                Assert::isArray($tdResponse);
                Assert::keyExists($tdResponse, 'id');
                Assert::keyExists($tdResponse, 'timeline');
                Assert::isArray($tdResponse['timeline']);
                Assert::keyExists($tdResponse['timeline'], 'price_min');
                Assert::keyExists($tdResponse['timeline'], 'price_max');
                Assert::keyExists($tdResponse['timeline'], 'first');

                $ad->addHistory(
                    (string) $tdResponse['id'],
                    (int) $tdResponse['timeline']['price_min'],
                    (int) $tdResponse['timeline']['price_max'],
                    CarbonImmutable::createFromTimestamp((string) $tdResponse['timeline']['first']),
                );
                $tdMessage = sprintf(
                    "€ min: %s\n€ max: %s\nFirst seen: %s\nhttps://tirgusdati.lv/app/listings/history/%s",
                    number_format($ad->priceMin, thousands_separator: ' '),
                    number_format($ad->priceMax, thousands_separator: ' '),
                    $ad->firstSeenAt->format('Y-m-d H:i:s'),
                    $ad->tdId,
                );
            } catch (GuzzleException | JsonException | InvalidArgumentException $exception) {
                $tdMessage = 'Could not fetch from TirgusDati: ' . $exception->getMessage();
                $this->logger->error($tdMessage);
            }

            $message = sprintf(
                "%s\nK: %s\nm2: %s\n€: %s\n%s\n%s",
                $ad->street,
                $ad->rooms,
                $ad->space,
                number_format($ad->price, thousands_separator: ' '),
                $ad->url,
                $tdMessage,
            );

            $this->logger->info('Found new ad', [
                'street' => $ad->street,
                'rooms' => $ad->rooms,
                'space' => $ad->space,
                'price' => $ad->price,
                'url' => $ad->url,
                'price_min' => $ad->priceMin ?? null,
                'price_max' => $ad->priceMax ?? null,
                'first_seen_at' => $ad->firstSeenAt->format('Y-m-d H:i:s'),
            ]);

            $this->logger->debug('Posting to Telegram', ['message' => $message]);
            if (! $doPostToTg) {
                continue;
            }

            $cookielessClient->post($this->tgUri . '&text=' . urlencode($message));
        }

        return 0;
    }
}
