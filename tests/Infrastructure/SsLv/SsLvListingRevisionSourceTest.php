<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

use App\Domain\Category;
use App\Domain\ListingRevisionCandidate;
use App\Infrastructure\SsLv\SsLvListingRevisionSource;
use App\Tests\Support\SsLvDescription;
use App\Tests\Support\SsLvFixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SsLvListingRevisionSourceTest extends TestCase
{
    public function testListingRevisionCandidateIsParsedForWatchProfile(): void
    {
        $description = SsLvDescription::apartment(rooms: 4, space: 90, price: '250,000 €');
        $source      = new SsLvListingRevisionSource(
            SsLvFixtures::parsers(),
            new NullLogger(),
            SsLvFixtures::rssClient(SsLvFixtures::rssFeed($description)),
        );

        $profile    = SsLvFixtures::apartmentProfile();
        $candidates = $source->candidates($profile, $profile->sourceUrls[0]);

        self::assertCount(1, $candidates);
        self::assertSame(SsLvFixtures::APARTMENT_URL, $candidates[0]->listing->url);
        self::assertSame(ListingRevisionCandidate::contentHash($description), $candidates[0]->contentHash);
    }

    public function testRssTitleIsPassedToParser(): void
    {
        $description = SsLvDescription::laptop(ram: 16, price: '899 €');
        $source      = new SsLvListingRevisionSource(
            SsLvFixtures::parsers(),
            new NullLogger(),
            SsLvFixtures::rssClient(SsLvFixtures::rssFeed(
                description: $description,
                url: 'https://www.ss.lv/msg/lv/electronics/computers/noutbooks/fxlge.html',
            )),
        );

        $profile    = SsLvFixtures::laptopProfile();
        $candidates = $source->candidates($profile, $profile->sourceUrls[0]);

        self::assertSame('Test listing title', $candidates[0]->listing->parsedFields['title']);
    }

    public function testParsesHeadphoneCandidates(): void
    {
        $description = SsLvDescription::headphones(
            brand: 'Sennheiser',
            condition: 'lietota',
            price: '250 €',
        );
        $source      = new SsLvListingRevisionSource(
            SsLvFixtures::parsers(),
            new NullLogger(),
            SsLvFixtures::rssClient(SsLvFixtures::rssFeed(
                description: $description,
                url: SsLvFixtures::HEADPHONE_URL,
                title: 'Sennheiser HD 660S 2',
                pubDate: 'Mon, 08 Jun 2026 18:55:13 +0300',
            )),
        );

        $profile    = SsLvFixtures::headphonesProfile();
        $candidates = $source->candidates($profile, $profile->sourceUrls[0]);

        self::assertCount(1, $candidates);
        self::assertSame(Category::Headphones, $candidates[0]->listing->category);
    }
}
