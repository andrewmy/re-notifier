<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\SsLv;

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

        $candidates = $source->candidates(SsLvFixtures::apartmentProfile());

        self::assertCount(1, $candidates);
        self::assertSame(SsLvFixtures::APARTMENT_URL, $candidates[0]->listing->url);
        self::assertSame(ListingRevisionCandidate::contentHash($description), $candidates[0]->contentHash);
    }
}
