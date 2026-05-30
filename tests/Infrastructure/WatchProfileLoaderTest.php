<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Domain\Category;
use App\Domain\WatchProfile;
use App\Infrastructure\WatchProfileLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_exists;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class WatchProfileLoaderTest extends TestCase
{
    private string|null $configFile = null;

    protected function tearDown(): void
    {
        if ($this->configFile === null || ! file_exists($this->configFile)) {
            return;
        }

        unlink($this->configFile);
    }

    public function testLoadsValidConfig(): void
    {
        $configFile = $this->writeConfig(<<<'PHP'
<?php
declare(strict_types=1);
use App\Domain\ApartmentCriteria;
use App\Domain\Category;
use App\Domain\WatchProfile;
return [
    new WatchProfile(
        id: 'test-apartments',
        category: Category::Apartment,
        rssUrl: 'https://www.ss.lv/ru/real-estate/flats/riga/all/rss/',
        criteria: new ApartmentCriteria(minRooms: 4, minSpace: 85, maxPrice: 260000),
    ),
];
PHP);

        $profiles = WatchProfileLoader::load($configFile);

        self::assertCount(1, $profiles);
        self::assertInstanceOf(WatchProfile::class, $profiles[0]);
        self::assertSame('test-apartments', $profiles[0]->id);
        self::assertSame(Category::Apartment, $profiles[0]->category);
    }

    public function testMissingConfigThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Watch profiles config not found');

        WatchProfileLoader::load('/nonexistent/path/watch_profiles.local.php');
    }

    public function testInvalidConfigThrows(): void
    {
        $configFile = $this->writeConfig('<?php return "not an array";');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must return an array of WatchProfile');

        WatchProfileLoader::load($configFile);
    }

    public function testLoadsCommittedExampleConfig(): void
    {
        $examplePath = __DIR__ . '/../../config/watch_profiles.example.php';

        $profiles = WatchProfileLoader::load($examplePath);

        self::assertNotEmpty($profiles);
        foreach ($profiles as $profile) {
            self::assertInstanceOf(WatchProfile::class, $profile);
        }
    }

    private function writeConfig(string $content): string
    {
        $this->configFile = tempnam(sys_get_temp_dir(), 'watch-profiles-test-');
        self::assertIsString($this->configFile);

        file_put_contents($this->configFile, $content);

        return $this->configFile;
    }
}
