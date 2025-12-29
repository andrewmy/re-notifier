<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Ad;
use App\Domain\AdRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class DbalAdRepository implements AdRepository
{
    private const string TABLE_NAME = 'ads';

    private Connection $db;

    public function __construct(
        string $dbDsn,
    ) {
        $this->db = DriverManager::getConnection(['url' => $dbDsn]);
        $this->db->executeQuery(
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
        $this->db->executeQuery(
            'CREATE UNIQUE INDEX IF NOT EXISTS ads_nat_key ON ads (url, description)',
        );
    }

    public function exists(string $url, string $description): bool
    {
        $id = $this->db->fetchOne(
            'SELECT id FROM ads WHERE url = ? AND description = ?',
            [$url, $description],
        );

        return $id !== false;
    }

    public function save(Ad $ad): void
    {
        $this->db->executeQuery(
            'INSERT INTO ' . self::TABLE_NAME . ' ('
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
    }
}
