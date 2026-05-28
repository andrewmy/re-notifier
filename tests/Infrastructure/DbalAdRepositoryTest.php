<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Domain\Ad;
use App\Infrastructure\DbalAdRepository;
use App\Tests\Support\SsLvDescription;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class DbalAdRepositoryTest extends TestCase
{
    private string|null $dbFile = null;

    protected function tearDown(): void
    {
        if ($this->dbFile === null || ! file_exists($this->dbFile)) {
            return;
        }

        unlink($this->dbFile);
    }

    public function testExistsIsFalseBeforeSaveAndTrueAfterSave(): void
    {
        $repository = $this->repository();
        $ad         = self::ad(
            url: 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html',
            description: SsLvDescription::apartment(street: 'Brivibas'),
        );

        self::assertFalse($repository->exists($ad->url, $ad->description));

        $repository->save($ad);

        self::assertTrue($repository->exists($ad->url, $ad->description));
    }

    public function testDuplicateSemanticsUseExactUrlAndDescription(): void
    {
        $repository  = $this->repository();
        $description = SsLvDescription::apartment(street: 'Brivibas');
        $url         = 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html';

        $repository->save(self::ad(url: $url, description: $description));

        self::assertTrue($repository->exists($url, $description));
        self::assertFalse($repository->exists($url . '?changed=1', $description));
        self::assertFalse($repository->exists($url, SsLvDescription::apartment(street: 'Tallinas')));
    }

    private function repository(): DbalAdRepository
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 're-notifier-test-');
        self::assertIsString($this->dbFile);

        return new DbalAdRepository('pdo-sqlite:///' . $this->dbFile);
    }

    private static function ad(string $url, string $description): Ad
    {
        return Ad::fromData(
            '2026-01-01 12:00:00',
            $url,
            $description,
        );
    }
}
