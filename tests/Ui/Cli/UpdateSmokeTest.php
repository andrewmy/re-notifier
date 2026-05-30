<?php

declare(strict_types=1);

namespace App\Tests\Ui\Cli;

use App\Domain\ApartmentCriteria;
use App\Domain\Category;
use App\Domain\WatchProfile;
use App\Infrastructure\SsLv\ApartmentParser;
use App\Infrastructure\SsLv\HouseParser;
use App\Infrastructure\SsLv\SsLvParser;
use App\Tests\Support\Spy\NullEnricher;
use App\Tests\Support\Spy\SpyListingRepository;
use App\Tests\Support\Spy\SpyNotifier;
use App\Tests\Support\SsLvDescription;
use App\Ui\Cli\Update;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class UpdateSmokeTest extends TestCase
{
    public function testDryRunMatchesApartmentButDoesNotSave(): void
    {
        $profile  = self::apartmentProfile();
        $rss      = self::rssFeed(SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $input  = new StringInput('--dry-run');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        self::assertSame(0, $result);
        self::assertFalse($repo->saved);
        self::assertEmpty($notifier->messages);
    }

    public function testRealModeMatchesApartmentSavesAndNotifies(): void
    {
        $profile  = self::apartmentProfile();
        $rss      = self::rssFeed(SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $input  = new StringInput('');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        self::assertSame(0, $result);
        self::assertTrue($repo->saved);
        self::assertCount(1, $notifier->messages);
        self::assertStringContainsString('#riga_family_apartments', $notifier->messages[0]);
    }

    public function testNonMatchingApartmentIsSkipped(): void
    {
        $profile  = self::apartmentProfile();
        $rss      = self::rssFeed(SsLvDescription::apartment(rooms: 2, space: 40, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $input  = new StringInput('');
        $output = new BufferedOutput();

        $command->run($input, $output);

        self::assertFalse($repo->saved);
        self::assertEmpty($notifier->messages);
    }

    public function testSeenListingIsSkipped(): void
    {
        $profile  = self::apartmentProfile();
        $desc     = SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €');
        $rss      = self::rssFeed($desc);
        $repo     = new SpyListingRepository(seen: true);
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $input  = new StringInput('');
        $output = new BufferedOutput();

        $command->run($input, $output);

        self::assertFalse($repo->saved);
        self::assertEmpty($notifier->messages);
    }

    public function testZeroPriceListingStillNotifiesAndSaves(): void
    {
        $profile  = self::apartmentProfile();
        $rss      = self::rssFeed(SsLvDescription::apartment(rooms: 4, space: 90, price: 'куплю'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $input  = new StringInput('');
        $output = new BufferedOutput();

        $command->run($input, $output);

        self::assertTrue($repo->saved);
        self::assertCount(1, $notifier->messages);
        self::assertStringContainsString('€: 0', $notifier->messages[0]);
    }

    public function testNotificationIncludesHashtagAndFields(): void
    {
        $profile  = self::apartmentProfile();
        $rss      = self::rssFeed(SsLvDescription::apartment(street: 'Brivibas', rooms: 4, space: 90, price: '250,000 €'));
        $repo     = new SpyListingRepository();
        $notifier = new SpyNotifier();

        $command = self::createCommand($profile, $rss, $repo, $notifier);

        $input  = new StringInput('');
        $output = new BufferedOutput();

        $command->run($input, $output);

        $msg = $notifier->messages[0];
        self::assertStringContainsString('#riga_family_apartments', $msg);
        self::assertStringContainsString('https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html', $msg);
        self::assertStringContainsString('Brivibas', $msg);
    }

    private static function createCommand(
        WatchProfile $profile,
        Response $rssResponse,
        SpyListingRepository $repo,
        SpyNotifier $notifier,
    ): Update {
        $client = new Client([
            'handler' => HandlerStack::create(new MockHandler([$rssResponse])),
        ]);

        return new Update(
            [$profile],
            self::parsers(),
            $repo,
            $notifier,
            new NullEnricher(),
            new NullLogger(),
            $client,
        );
    }

    private static function apartmentProfile(): WatchProfile
    {
        return new WatchProfile(
            id: 'riga-family-apartments',
            category: Category::Apartment,
            rssUrl: 'https://www.ss.lv/ru/real-estate/flats/riga/all/rss/',
            criteria: new ApartmentCriteria(minRooms: 4, minSpace: 85, maxPrice: 260000),
        );
    }

    /** @return array<string, SsLvParser> */
    private static function parsers(): array
    {
        return [
            Category::Apartment->value => new ApartmentParser(),
            Category::House->value => new HouseParser(),
        ];
    }

    private static function rssFeed(string $description, string $url = 'https://www.ss.lv/msg/ru/real-estate/flats/riga/centre/example.html'): Response
    {
        return new Response(
            body: <<<XML
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
<channel><title>Test</title>
<item>
    <link>{$url}</link>
    <pubDate>Thu, 28 May 2026 16:38:34 +0300</pubDate>
    <description><![CDATA[{$description}]]></description>
</item>
</channel>
</rss>
XML,
        );
    }
}
