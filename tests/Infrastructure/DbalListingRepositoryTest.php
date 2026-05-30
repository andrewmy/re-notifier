<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Domain\ApartmentListing;
use App\Infrastructure\DbalListingRepository;
use App\Tests\Support\SsLvDescription;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

use function file_exists;
use function md5;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class DbalListingRepositoryTest extends TestCase
{
    private string|null $dbFile = null;

    protected function tearDown(): void
    {
        if ($this->dbFile === null || ! file_exists($this->dbFile)) {
            return;
        }

        unlink($this->dbFile);
    }

    public function testIsSeenReturnsFalseBeforeSaveAndTrueAfterSave(): void
    {
        $repository = $this->repository();
        $listing    = self::apartmentListing();
        $hash       = self::hash($listing->description);

        self::assertFalse($repository->isSeen('riga-family-apartments', $listing->url, $hash));

        $repository->save($listing, 'riga-family-apartments', $hash);

        self::assertTrue($repository->isSeen('riga-family-apartments', $listing->url, $hash));
    }

    public function testSameUrlHashIsSeenForOneProfileButNotAnother(): void
    {
        $repository = $this->repository();
        $listing    = self::apartmentListing();
        $hash       = self::hash($listing->description);

        $repository->save($listing, 'riga-family-apartments', $hash);

        self::assertTrue($repository->isSeen('riga-family-apartments', $listing->url, $hash));
        self::assertFalse($repository->isSeen('riga-lenient-apartments', $listing->url, $hash));
    }

    public function testSameUrlWithDifferentContentHashIsNotSeen(): void
    {
        $repository = $this->repository();
        $listing1   = self::apartmentListing(url: 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html', description: SsLvDescription::apartment(street: 'Brivibas'));
        $listing2   = self::apartmentListing(url: 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html', description: SsLvDescription::apartment(street: 'Tallinas'));

        $hash1 = self::hash($listing1->description);
        $hash2 = self::hash($listing2->description);

        self::assertNotSame($hash1, $hash2);

        $repository->save($listing1, 'riga-family-apartments', $hash1);

        self::assertTrue($repository->isSeen('riga-family-apartments', $listing1->url, $hash1));
        self::assertFalse($repository->isSeen('riga-family-apartments', $listing1->url, $hash2));
    }

    public function testMigratesExistingAdsTable(): void
    {
        $dbFile       = tempnam(sys_get_temp_dir(), 're-notifier-migration-');
        $this->dbFile = $dbFile;

        $this->createOldAdsTable($dbFile);

        $repository = new DbalListingRepository('pdo-sqlite:///' . $dbFile);

        $hash = self::hash('old description');

        self::assertTrue($repository->isSeen('riga-family-apartments', 'https://old-url.example.com', $hash));
    }

    public function testFindRawDescriptionsByUrl(): void
    {
        $repository = $this->repository();
        $url        = 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html';
        $listing    = self::apartmentListing(url: $url);
        $hash       = self::hash($listing->description);

        $repository->save($listing, 'riga-family-apartments', $hash);

        $rows = $repository->findRawDescriptionsByUrl($url);

        self::assertCount(1, $rows);
        self::assertSame($listing->description, $rows[0]['description']);
    }

    public function testFindRawDescriptionsReturnsEmptyForUnknownUrl(): void
    {
        $repository = $this->repository();

        $rows = $repository->findRawDescriptionsByUrl('https://unknown.example.com');

        self::assertSame([], $rows);
    }

    private function repository(): DbalListingRepository
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 're-notifier-test-');
        self::assertIsString($this->dbFile);

        return new DbalListingRepository('pdo-sqlite:///' . $this->dbFile);
    }

    private function createOldAdsTable(string $dbFile): void
    {
        $db = DriverManager::getConnection(new DsnParser()->parse('pdo-sqlite:///' . $dbFile));
        $db->executeQuery(
            'CREATE TABLE ads ('
            . 'id TEXT NOT NULL PRIMARY KEY, '
            . 'updated_at INTEGER NOT NULL, '
            . 'published_at INTEGER NOT NULL, '
            . 'url TEXT NOT NULL, '
            . 'rooms INTEGER NOT NULL, '
            . '`space` INTEGER NOT NULL, '
            . 'price INTEGER NOT NULL, '
            . 'description TEXT NOT NULL'
            . ')',
        );
        $db->executeQuery(
            'CREATE UNIQUE INDEX ads_nat_key ON ads (url, description)',
        );
        $db->executeQuery(
            'INSERT INTO ads (id, updated_at, published_at, url, rooms, `space`, price, description) '
            . "VALUES ('old-id', 1700000000, 1700000000, 'https://old-url.example.com', 4, 90, 250000, 'old description')",
        );
    }

    private static function apartmentListing(
        string $url = 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html',
        string $description = '',
    ): ApartmentListing {
        $desc = $description ?: SsLvDescription::apartment(street: 'Brivibas');

        return new ApartmentListing(
            id: new Ulid(),
            url: $url,
            description: $desc,
            imageUrl: null,
            publishedAt: CarbonImmutable::now(),
            storedAt: CarbonImmutable::now(),
            price: 250000,
            rooms: 4,
            space: 90,
            street: 'Brivibas',
        );
    }

    private static function hash(string $description): string
    {
        return md5(str_replace("\n", '', $description));
    }
}
