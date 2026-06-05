<?php

declare(strict_types=1);

namespace App\Domain;

use function array_any;
use function preg_match;
use function preg_quote;

final class LaptopCriteria implements Criteria
{
    /**
     * @param list<string> $titleIncludesAny
     * @param list<string> $titleExcludesAny
     * @param list<string> $allowedBrands
     */
    public function __construct(
        public readonly int|null $maxPrice = null,
        public readonly int $minRamGb = 0,
        public readonly int $minStorageGb = 0,
        public readonly int $minDisplayInches = 0,
        public readonly int $maxDisplayInches = 0,
        public readonly array $titleIncludesAny = [],
        public readonly array $titleExcludesAny = [],
        public readonly array $allowedBrands = [],
    ) {
    }

    public Category $category { get => Category::Laptop; }

    public function matches(Listing $listing): bool
    {
        if ($listing->category !== Category::Laptop) {
            return false;
        }

        $title         = (string) ($listing->parsedFields['title'] ?? '');
        $brand         = (string) ($listing->parsedFields['brand'] ?? '');
        $displayInches = (int) ($listing->parsedFields['displayInches'] ?? 0);

        return ($this->maxPrice === null || $listing->price <= $this->maxPrice || $listing->price === 0)
            && (int) ($listing->parsedFields['ramGb'] ?? 0) >= $this->minRamGb
            && (int) ($listing->parsedFields['storageGb'] ?? 0) >= $this->minStorageGb
            && ($this->minDisplayInches === 0 || $displayInches >= $this->minDisplayInches)
            && ($this->maxDisplayInches === 0 || $displayInches <= $this->maxDisplayInches)
            && self::matchesAnyConfigured($brand, $this->allowedBrands)
            && self::matchesAnyConfigured($title, $this->titleIncludesAny)
            && ! self::containsAny($title, $this->titleExcludesAny);
    }

    /** @param list<string> $needles */
    private static function matchesAnyConfigured(string $haystack, array $needles): bool
    {
        if ($needles === []) {
            return true;
        }

        return self::containsAny($haystack, $needles);
    }

    /** @param list<string> $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        return array_any($needles, static fn ($needle) => $needle !== '' && preg_match('/' . preg_quote((string) $needle, '/') . '/iu', $haystack) === 1);
    }
}
