<?php

declare(strict_types=1);

namespace App\Tests\Ui\Cli;

use App\Application\ListingRevisionIntake;
use App\Domain\WatchProfile;
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
                new SsLvListingRevisionSource(
                    SsLvFixtures::parsers(),
                    new NullLogger(),
                    $client,
                ),
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
