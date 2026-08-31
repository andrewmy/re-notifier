<?php

declare(strict_types=1);

namespace App\Ui\Cli;

use App\Application\ListingEnricher;
use App\Application\ListingRevisionIntake;
use App\Application\Notifier;
use App\Domain\Category;
use App\Domain\Listing;
use App\Domain\ListingRepository;
use App\Domain\ListingRevisionSourceFailed;
use App\Domain\WatchProfile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function implode;
use function number_format;

final class Update extends Command
{
    /** @param list<WatchProfile> $watchProfiles */
    public function __construct(
        private readonly array $watchProfiles,
        private readonly ListingRevisionIntake $listingRevisionIntake,
        private readonly ListingRepository $listingRepository,
        private readonly Notifier $notifier,
        private readonly ListingEnricher $enricher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct('update');
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'dry-run',
            description: 'Do not send Telegram notifications and do not save revisions',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        foreach ($this->watchProfiles as $profile) {
            try {
                $pendingRevisions = $this->listingRevisionIntake->pendingRevisions($profile);
            } catch (ListingRevisionSourceFailed $exception) {
                $this->logger->error($exception->getMessage());

                return 1;
            }

            foreach ($pendingRevisions as $pendingRevision) {
                $listing = $pendingRevision->listing;
                $message = $this->formatMessage($listing, $profile);

                $this->logger->info('Found matching listing', [
                    'url' => $listing->url,
                    'profile' => $profile->id,
                    'price' => $listing->price,
                    'parsedFields' => $listing->parsedFields,
                    'imageUrl' => $listing->imageUrl,
                ]);

                if ($dryRun) {
                    $this->logger->debug('Dry run: skipping send and save', ['message' => $message]);

                    continue;
                }

                $this->notifier->send($message, $listing->imageUrl);
                $this->listingRepository->save($listing, $profile->id, $pendingRevision->contentHash);
            }
        }

        return 0;
    }

    private function formatMessage(Listing $listing, WatchProfile $profile): string
    {
        $enrichment = $this->enricher->enrich($listing);

        $lines = [
            $profile->hashtag,
            $listing->url,
        ];

        foreach ($listing->parsedFields as $label => $value) {
            if ($label === 'price' || $label === 'landAreaRaw' || ($listing->category === Category::House && $label === 'rooms')) {
                continue;
            }

            $strValue = (string) $value;

            if ($strValue === '' || $strValue === '0') {
                continue;
            }

            if ($label === 'landArea') {
                $lines[] = 'land: ' . $strValue . ' m²';

                continue;
            }

            if ($label === 'space') {
                $lines[] = 'space: ' . $strValue . ' m²';

                continue;
            }

            $lines[] = $label . ': ' . $strValue;
        }

        $lines[] = '€: ' . number_format($listing->price, thousands_separator: ' ');

        if ($enrichment !== null) {
            $lines[] = 'TirgusDati URL history:';

            if ($enrichment->historyMismatch) {
                $lines[] = '⚠ TirgusDati history appears to belong to a different listing previously using this SS.lv URL.';
            }

            $lines[] = '€ min: ' . number_format($enrichment->priceMin, thousands_separator: ' ');
            $lines[] = '€ max: ' . number_format($enrichment->priceMax, thousands_separator: ' ');
            $lines[] = 'First seen: ' . $enrichment->firstSeenAt->format('Y-m-d');
            $lines[] = 'Current listing history:';
            $lines[] = $enrichment->historyUrl;

            foreach ($enrichment->previousHistoryUrls as $previousHistoryUrl) {
                $lines[] = 'Previous matching listing history:';
                $lines[] = $previousHistoryUrl;
            }
        }

        return implode("\n", $lines);
    }
}
