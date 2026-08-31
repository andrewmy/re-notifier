<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Listing;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\XPath;

use function array_filter;
use function count;
use function preg_replace;
use function round;
use function str_replace;
use function trim;

final readonly class TirgusDatiListingMatcher
{
    public function historyBelongsToDifferentListing(Listing $listing, HTMLDocument $document): bool
    {
        $xpath       = new XPath($document);
        $addressNode = $xpath->query(
            "//article[contains(concat(' ', normalize-space(@class), ' '), ' listing ')]"
            . "//div[contains(concat(' ', normalize-space(@class), ' '), ' listing-head ')]"
            . "/following-sibling::p[contains(concat(' ', normalize-space(@class), ' '), ' muted ')][1]",
        )->item(0);

        $specs = [];
        foreach ($xpath->query("//dl[contains(concat(' ', normalize-space(@class), ' '), ' spec-list ')]/div") as $row) {
            if (! $row instanceof Element) {
                continue;
            }

            $labelNode = $xpath->query('./dt', $row)->item(0);
            $valueNode = $xpath->query('./dd', $row)->item(0);
            $label     = $labelNode === null ? '' : trim($labelNode->textContent ?? '');
            $value     = $valueNode === null ? '' : trim($valueNode->textContent ?? '');
            if ($label === '' || $value === '') {
                continue;
            }

            $specs[$label] = $value;
        }

        $comparisons = [];
        if ($addressNode !== null && ($listing->parsedFields['street'] ?? '') !== '') {
            $comparisons[] = self::normalizeAddress($addressNode->textContent ?? '')
                === self::normalizeAddress((string) $listing->parsedFields['street']);
        }

        if (isset($specs['Istabas'], $listing->parsedFields['rooms'])) {
            $comparisons[] = (int) preg_replace('/\D/u', '', $specs['Istabas'])
                === (int) $listing->parsedFields['rooms'];
        }

        if (isset($specs['Platība'], $listing->parsedFields['space'])) {
            $area          = (float) str_replace(',', '.', (string) preg_replace('/[^0-9,.]/u', '', $specs['Platība']));
            $comparisons[] = (int) round($area) === (int) $listing->parsedFields['space'];
        }

        $differences = count(array_filter($comparisons, static fn (bool $matches): bool => ! $matches));

        return count($comparisons) >= 2 && $differences >= 2;
    }

    private static function normalizeAddress(string $address): string
    {
        $withoutCity = preg_replace('/,\s*[^,]+$/u', '', trim($address));

        return trim((string) preg_replace('/\s+/u', ' ', (string) $withoutCity));
    }
}
