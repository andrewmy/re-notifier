<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Listing;
use App\Domain\ListingRepository;
use App\Domain\ListingRevisionCandidate;
use App\Infrastructure\SsLv\SsLvFieldExtractor;
use App\Infrastructure\SsLv\SsLvPropertyFingerprint;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

use function array_any;
use function array_keys;
use function json_encode;
use function parse_url;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_HOST;

final readonly class DbalListingRepository implements ListingRepository
{
    private const string TABLE_NAME = 'listing_revisions';

    private Connection $db;

    public function __construct(string $dbDsn)
    {
        $this->db = DriverManager::getConnection(new DsnParser()->parse($dbDsn));
        $this->migrate();
    }

    public function isSeen(string $watchProfileId, string $url, string $contentHash): bool
    {
        $id = $this->db->fetchOne(
            'SELECT id FROM ' . self::TABLE_NAME . ' WHERE watch_profile_id = ? AND url = ? AND content_hash = ?',
            [$watchProfileId, $url, $contentHash],
        );

        if ($id !== false) {
            return true;
        }

        if (parse_url($url, PHP_URL_HOST) !== 'www.ss.lv') {
            return false;
        }

        // Older SS.lv rows hashed the description with the price highlight still present.
        $descriptions = $this->db->fetchFirstColumn(
            'SELECT description FROM ' . self::TABLE_NAME
            . ' WHERE watch_profile_id = ? AND url = ? AND description LIKE ?',
            [$watchProfileId, $url, '%<span class="green bold">%'],
        );

        return array_any($descriptions, static fn ($description) => ListingRevisionCandidate::contentHash(SsLvFieldExtractor::withoutPriceHighlight((string) $description)) === $contentHash);
    }

    public function save(Listing $listing, string $watchProfileId, string $contentHash): void
    {
        $this->db->executeQuery(
            'INSERT INTO ' . self::TABLE_NAME . ' ('
            . 'id, stored_at, published_at, url, price, description, '
            . 'watch_profile_id, category, content_hash, parsed_fields, image_url'
            . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $listing->id->toBase58(),
                $listing->storedAt->getTimestamp(),
                $listing->publishedAt->getTimestamp(),
                $listing->url,
                $listing->price,
                $listing->description,
                $watchProfileId,
                $listing->category->value,
                $contentHash,
                json_encode($listing->parsedFields, JSON_THROW_ON_ERROR),
                $listing->imageUrl,
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public function findRawDescriptionsByUrl(string $url): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT description, watch_profile_id, stored_at FROM ' . self::TABLE_NAME . ' WHERE url = ? ORDER BY stored_at DESC',
            [$url],
        );
    }

    /** @return list<string> */
    public function findPreviousUrlsForSameProperty(Listing $listing): array
    {
        $currentFingerprint = SsLvPropertyFingerprint::fromDescription($listing->category, $listing->description);
        if ($currentFingerprint === null) {
            return [];
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT url, description FROM ' . self::TABLE_NAME
            . ' WHERE category = ? ORDER BY stored_at DESC',
            [$listing->category->value],
        );

        $matchedUrls = [];
        foreach ($rows as $row) {
            $url = (string) $row['url'];
            if ($url === $listing->url || isset($matchedUrls[$url])) {
                continue;
            }

            $storedFingerprint = SsLvPropertyFingerprint::fromDescription(
                $listing->category,
                (string) $row['description'],
            );
            if ($storedFingerprint !== $currentFingerprint) {
                continue;
            }

            $matchedUrls[$url] = true;
        }

        return array_keys($matchedUrls);
    }

    private function migrate(): void
    {
        $this->migrateFromAds();
        $this->createTable();
        $this->createUniqueIndex();
    }

    private function createTable(): void
    {
        $this->db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_NAME . ' ('
            . 'id TEXT NOT NULL PRIMARY KEY, '
            . 'stored_at INTEGER NOT NULL, '
            . 'published_at INTEGER NOT NULL, '
            . 'url TEXT NOT NULL, '
            . 'price INTEGER NOT NULL, '
            . 'description TEXT NOT NULL, '
            . 'watch_profile_id TEXT NOT NULL, '
            . 'category TEXT NOT NULL, '
            . 'content_hash TEXT NOT NULL, '
            . 'parsed_fields TEXT NOT NULL, '
            . 'image_url TEXT NULL'
            . ')',
        );
    }

    private function createUniqueIndex(): void
    {
        $this->db->executeQuery(
            'CREATE UNIQUE INDEX IF NOT EXISTS listing_revision_nat_key ON ' . self::TABLE_NAME
            . ' (watch_profile_id, url, content_hash)',
        );
    }

    private function migrateFromAds(): void
    {
        $schemaManager = $this->db->createSchemaManager();
        if (! $schemaManager->tablesExist(['ads'])) {
            return;
        }

        $rows = $this->db->fetchAllAssociative('SELECT * FROM ads');

        $this->createTable();

        foreach ($rows as $row) {
            $description = (string) $row['description'];
            $url         = (string) $row['url'];
            $contentHash = ListingRevisionCandidate::contentHash($description);

            $existing = $this->db->fetchOne(
                'SELECT id FROM ' . self::TABLE_NAME . ' WHERE watch_profile_id = ? AND url = ? AND content_hash = ?',
                ['riga-family-apartments', $url, $contentHash],
            );

            if ($existing !== false) {
                continue;
            }

            $parsedFields = json_encode([
                'rooms' => (int) $row['rooms'],
                'space' => (int) $row['space'],
            ], JSON_THROW_ON_ERROR);

            $this->db->executeQuery(
                'INSERT INTO ' . self::TABLE_NAME . ' ('
                . 'id, stored_at, published_at, url, price, description, '
                . 'watch_profile_id, category, content_hash, parsed_fields, image_url'
                . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $row['id'],
                    (int) $row['updated_at'],
                    (int) $row['published_at'],
                    $url,
                    (int) $row['price'],
                    $description,
                    'riga-family-apartments',
                    'apartment',
                    $contentHash,
                    $parsedFields,
                    SsLvFieldExtractor::imageUrl($description),
                ],
            );
        }

        $this->db->executeQuery('DROP TABLE ads');
    }
}
