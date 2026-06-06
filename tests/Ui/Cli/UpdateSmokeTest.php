<?php

declare(strict_types=1);

namespace App\Tests\Ui\Cli;

use App\Application\ListingRevisionIntake;
use App\Domain\Category;
use App\Domain\LaptopCriteria;
use App\Domain\WatchProfile;
use App\Infrastructure\Banknote\BanknoteInventoryRevisionSource;
use App\Infrastructure\ListingRevisionSourceRouter;
use App\Infrastructure\SsLv\SsLvListingRevisionSource;
use App\Tests\Support\Spy\NullEnricher;
use App\Tests\Support\Spy\SpyListingRepository;
use App\Tests\Support\Spy\SpyNotifier;
use App\Tests\Support\SsLvDescription;
use App\Tests\Support\SsLvFixtures;
use App\Ui\Cli\Update;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class UpdateSmokeTest extends TestCase
{
    public function testDryRunMatchesApartmentButDoesNotSave(): void
    {
        $profile  = SsLvFixtures::apartmentProfile();
        $rss      = SsLvFixtures::rssFeed(SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $result = self::runCommand($command, '--dry-run');

        self::assertSame(0, $result);
        self::assertFalse($repo->saved);
        self::assertEmpty($notifier->messages);
    }

    public function testRealModeMatchesApartmentSavesAndNotifies(): void
    {
        $profile  = SsLvFixtures::apartmentProfile();
        $rss      = SsLvFixtures::rssFeed(SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $result = self::runCommand($command);

        self::assertSame(0, $result);
        self::assertTrue($repo->saved);
        self::assertCount(1, $notifier->messages);
        self::assertStringContainsString('#riga_family_apartments', $notifier->messages[0]);
    }

    public function testNonMatchingApartmentIsSkipped(): void
    {
        $profile  = SsLvFixtures::apartmentProfile();
        $rss      = SsLvFixtures::rssFeed(SsLvDescription::apartment(rooms: 2, space: 40, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        self::runCommand($command);

        self::assertFalse($repo->saved);
        self::assertEmpty($notifier->messages);
    }

    public function testSeenListingIsSkipped(): void
    {
        $profile  = SsLvFixtures::apartmentProfile();
        $desc     = SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €');
        $rss      = SsLvFixtures::rssFeed($desc);
        $repo     = new SpyListingRepository(seen: true);
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        self::runCommand($command);

        self::assertFalse($repo->saved);
        self::assertEmpty($notifier->messages);
    }

    public function testZeroPriceListingStillNotifiesAndSaves(): void
    {
        $profile  = SsLvFixtures::apartmentProfile();
        $rss      = SsLvFixtures::rssFeed(SsLvDescription::apartment(rooms: 4, space: 90, price: 'куплю'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        self::runCommand($command);

        self::assertTrue($repo->saved);
        self::assertCount(1, $notifier->messages);
        self::assertStringContainsString('€: 0', $notifier->messages[0]);
    }

    public function testNotificationIncludesHashtagAndFields(): void
    {
        $profile  = SsLvFixtures::apartmentProfile();
        $rss      = SsLvFixtures::rssFeed(SsLvDescription::apartment(street: 'Brivibas', rooms: 4, space: 90, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        self::runCommand($command);

        $msg = $notifier->messages[0];
        self::assertStringContainsString('#riga_family_apartments', $msg);
        self::assertStringContainsString(SsLvFixtures::APARTMENT_URL, $msg);
        self::assertStringContainsString('Brivibas', $msg);
    }

    public function testRealModeMatchesLaptopFromSsLvAndBanknote(): void
    {
        $profile  = new WatchProfile(
            id: 'apple-laptops',
            category: Category::Laptop,
            sourceUrls: [
                'https://www.ss.lv/lv/electronics/computers/noutbooks/sell/rss/',
                'https://banknote.example.test/inventory/laptops/normalized.json',
            ],
            criteria: new LaptopCriteria(
                maxPrice: 1000,
                minRamGb: 16,
                minStorageGb: 512,
                brands: ['Apple'],
            ),
        );
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();
        $command  = self::createMultiSourceLaptopCommand(
            $profile,
            SsLvFixtures::rssFeed(
                SsLvDescription::laptop(model: 'MacBook Air M4', storage: 512, ram: 16, price: '899 €'),
                'https://www.ss.lv/msg/lv/electronics/computers/noutbooks/example.html',
            ),
            new Response(body: json_encode([
                'inventory' => [
                    [
                        'article' => 12345,
                        'id' => 67890,
                        'title' => 'Apple MacBook Air 13 M4',
                        'price' => 899.0,
                        'cpu' => 'Apple M4',
                        'ram' => '16GB',
                        'storage' => '512GB SSD',
                        'url' => 'https://veikals.banknote.lv/lv/products/apple-macbook-air-67890',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            $repo,
            $notifier,
        );

        $result = self::runCommand($command);

        self::assertSame(0, $result);
        self::assertTrue($repo->saved);
        self::assertCount(2, $notifier->messages);
        self::assertStringContainsString('https://www.ss.lv/msg/lv/electronics/computers/noutbooks/example.html', $notifier->messages[0]);
        self::assertStringContainsString('https://veikals.banknote.lv/lv/products/apple-macbook-air-67890', $notifier->messages[1]);
        self::assertStringContainsString('cpu: Apple M4', $notifier->messages[1]);
        self::assertStringNotContainsString("brand: \n", $notifier->messages[1]);
        self::assertStringNotContainsString('displayInches: 0', $notifier->messages[1]);
    }

    private static function createCommand(
        WatchProfile $profile,
        Response $rssResponse,
        SpyListingRepository $repo,
        SpyNotifier $notifier,
    ): Update {
        $client = SsLvFixtures::rssClient($rssResponse);

        return new Update(
            [$profile],
            new ListingRevisionIntake(
                new ListingRevisionSourceRouter([
                    new SsLvListingRevisionSource(
                        SsLvFixtures::parsers(),
                        new NullLogger(),
                        $client,
                    ),
                ]),
                $repo,
                new NullLogger(),
            ),
            $repo,
            $notifier,
            new NullEnricher(),
            new NullLogger(),
        );
    }

    private static function createMultiSourceLaptopCommand(
        WatchProfile $profile,
        Response $rssResponse,
        Response $banknoteResponse,
        SpyListingRepository $repo,
        SpyNotifier $notifier,
    ): Update {
        return new Update(
            [$profile],
            new ListingRevisionIntake(
                new ListingRevisionSourceRouter([
                    new SsLvListingRevisionSource(
                        SsLvFixtures::parsers(),
                        new NullLogger(),
                        SsLvFixtures::rssClient($rssResponse),
                    ),
                    new BanknoteInventoryRevisionSource(SsLvFixtures::rssClient($banknoteResponse)),
                ]),
                $repo,
                new NullLogger(),
            ),
            $repo,
            $notifier,
            new NullEnricher(),
            new NullLogger(),
        );
    }

    private static function runCommand(Update $command, string $input = ''): int
    {
        return $command->run(new StringInput($input), new BufferedOutput());
    }
}
