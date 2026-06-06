<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure;

use App\Domain\ApartmentCriteria;
use App\Domain\Category;
use App\Domain\ListingRevisionCandidate;
use App\Domain\ListingRevisionSourceFailed;
use App\Domain\WatchProfile;
use App\Infrastructure\ListingRevisionSourceRouter;
use App\Infrastructure\SelectableListingRevisionSource;
use PHPUnit\Framework\TestCase;

use function str_contains;

final class ListingRevisionSourceRouterTest extends TestCase
{
    public function testRoutesToSupportingSourceUrl(): void
    {
        $profile = self::profile();
        $source  = new class implements SelectableListingRevisionSource {
            public function supports(string $sourceUrl): bool
            {
                return str_contains($sourceUrl, 'banknote.example.test');
            }

            /** @return list<ListingRevisionCandidate> */
            public function candidates(WatchProfile $watchProfile, string $sourceUrl): array
            {
                return [];
            }
        };

        $router = new ListingRevisionSourceRouter([$source]);

        self::assertSame([], $router->candidates($profile, 'https://banknote.example.test/inventory/laptops/normalized.json'));
    }

    public function testFailsWhenNoSourceSupportsUrl(): void
    {
        $router = new ListingRevisionSourceRouter([]);

        $this->expectException(ListingRevisionSourceFailed::class);
        $this->expectExceptionMessage('No listing revision source supports https://example.com/feed');

        $router->candidates(self::profile(), 'https://example.com/feed');
    }

    private static function profile(): WatchProfile
    {
        return new WatchProfile(
            id: 'test-profile',
            category: Category::Apartment,
            sourceUrls: ['https://example.com/feed'],
            criteria: new ApartmentCriteria(minRooms: 1, minSpace: 1, maxPrice: 1),
        );
    }
}
