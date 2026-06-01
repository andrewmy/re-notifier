<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\ListingRevisionIntake;
use App\Domain\ApartmentListing;
use App\Domain\ListingRevisionCandidate;
use App\Domain\ListingRevisionSource;
use App\Domain\WatchProfile;
use App\Tests\Support\Spy\SpyListingRepository;
use App\Tests\Support\SsLvFixtures;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Ulid;

final class ListingRevisionIntakeTest extends TestCase
{
    public function testMatchingUnseenListingRevisionIsReturnedForWatchProfile(): void
    {
        $profile = SsLvFixtures::apartmentProfile();
        $listing = new ApartmentListing(
            id: new Ulid(),
            url: SsLvFixtures::APARTMENT_URL,
            description: 'description',
            imageUrl: null,
            publishedAt: CarbonImmutable::parse('2026-05-28 16:38:34'),
            storedAt: CarbonImmutable::parse('2026-05-28 16:38:34'),
            price: 250000,
            rooms: 4,
            space: 90,
            street: 'Brivibas',
        );
        $intake  = new ListingRevisionIntake(
            new class ($listing) implements ListingRevisionSource {
                public function __construct(private readonly ApartmentListing $listing)
                {
                }

                /** @return list<ListingRevisionCandidate> */
                public function candidates(WatchProfile $watchProfile): array
                {
                    return [new ListingRevisionCandidate($this->listing, 'hash')];
                }
            },
            new SpyListingRepository(),
            new NullLogger(),
        );

        $revisions = $intake->pendingRevisions($profile);

        self::assertCount(1, $revisions);
        self::assertSame($listing, $revisions[0]->listing);
        self::assertSame('hash', $revisions[0]->contentHash);
    }
}
