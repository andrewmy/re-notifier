<?php

declare(strict_types=1);

namespace App\Domain;

use function array_any;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function str_contains;
use function strtolower;

final class HeadphoneCriteria implements Criteria
{
    /**
     * @param list<string> $modelIncludesAny
     * @param list<string> $brands
     * @param list<string> $conditions
     * @param list<string> $titleExcludesAny
     */
    public function __construct(
        public readonly int|null $maxPrice = null,
        public readonly array $modelIncludesAny = [],
        public readonly array $brands = [],
        public readonly array $conditions = [],
        public readonly array $titleExcludesAny = [],
    ) {
    }

    public Category $category { get => Category::Headphones; }

    public function matches(Listing $listing): bool
    {
        if ($listing->category !== Category::Headphones) {
            return false;
        }

        $title      = (string) ($listing->parsedFields['title'] ?? '');
        $brand      = (string) ($listing->parsedFields['brand'] ?? '');
        $condition  = (string) ($listing->parsedFields['condition'] ?? '');
        $searchText = $title . "\n" . $listing->description;
        $modelText  = self::normalizeModelText($searchText);
        $brandText  = $brand === '' ? $title : $brand;

        return ($this->maxPrice === null || $listing->price <= $this->maxPrice || $listing->price === 0)
            && self::matchesAnyNormalizedModel($modelText, $this->modelIncludesAny)
            && self::matchesAnyConfigured($brandText, $this->brands)
            && self::matchesAnyConfigured($condition, $this->conditions)
            && ! self::containsAny($searchText, $this->titleExcludesAny);
    }

    /** @param list<string> $needles */
    private static function matchesAnyConfigured(string $haystack, array $needles): bool
    {
        if ($needles === []) {
            return true;
        }

        return self::containsAny($haystack, $needles);
    }

    /** @param list<string> $models */
    private static function matchesAnyNormalizedModel(string $normalizedText, array $models): bool
    {
        if ($models === []) {
            return true;
        }

        return array_any(
            $models,
            static function ($model) use ($normalizedText): bool {
                $normalizedModel = self::normalizeModelText((string) $model);

                return $normalizedModel !== '' && str_contains($normalizedText, $normalizedModel);
            },
        );
    }

    /** @param list<string> $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        return array_any($needles, static fn ($needle) => $needle !== '' && preg_match('/' . preg_quote((string) $needle, '/') . '/iu', $haystack) === 1);
    }

    private static function normalizeModelText(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', strtolower($text));
    }
}
