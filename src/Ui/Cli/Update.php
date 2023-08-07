<?php

declare(strict_types=1);

namespace App\Ui\Cli;

use App\Domain\Ad;
use Doctrine\DBAL\DriverManager;
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

use function number_format;
use function sprintf;
use function urlencode;

use const LIBXML_NOCDATA;

final class Update extends Command
{
    private const TABLE_NAME = 'ads';

    public function __construct(
        private string $tgUri,
        private string $rssUrl,
        private string $dbDsn,
        private LoggerInterface $logger,
    ) {
        parent::__construct('update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = DriverManager::getConnection(['url' => $this->dbDsn]);
        $db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_NAME . ' ('
            . 'id TEXT NOT NULL PRIMARY KEY, '
            . 'updated_at INTEGER NOT NULL, '
            . 'published_at INTEGER NOT NULL, '
            . 'url TEXT NOT NULL, '
            . 'rooms INTEGER NOT NULL, '
            . '`space` INTEGER NOT NULL, '
            . 'price INTEGER NOT NULL, '
            . 'description TEXT NOT NULL '
            . ')',
        );
        $db->executeQuery(
            'CREATE UNIQUE INDEX IF NOT EXISTS ads_nat_key ON ads (url, description)',
        );

        $client = new Client();

        try {
            $rssFeedBody = (string) $client->get($this->rssUrl)->getBody();
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
                Assert::isInstanceOf($item, SimpleXMLElement::class);
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

            $id = $db->fetchOne(
                'SELECT id FROM ads WHERE url = ? AND description = ?',
                [$url, $description],
            );

            if ($id !== false) {
                continue;
            }

            $ad = Ad::fromData(
                (string) $item->pubDate,
                $url,
                $description,
            );

            if (! $ad->matches()) {
                continue;
            }

            $db->executeQuery(
                'INSERT INTO ads ('
                . 'id, updated_at, published_at, url, rooms, `space`, price, description'
                . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $ad->id->toBase58(),
                    $ad->updatedAt->getTimestamp(),
                    $ad->publishedAt->getTimestamp(),
                    $ad->url,
                    $ad->rooms,
                    $ad->space,
                    $ad->price,
                    $ad->description,
                ],
            );

            $message = sprintf(
                "%s\nK: %s\nm2: %s\n€: %s\n%s",
                $ad->street,
                $ad->rooms,
                $ad->space,
                number_format($ad->price, thousands_separator: ' '),
                $ad->url,
            );

            $this->logger->info('Found new ad', [
                'street' => $ad->street,
                'rooms' => $ad->rooms,
                'space' => $ad->space,
                'price' => $ad->price,
                'url' => $ad->url,
            ]);

            $client->post($this->tgUri . '&text=' . urlencode($message));
        }

        return 0;
    }
}
