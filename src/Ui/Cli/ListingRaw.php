<?php

declare(strict_types=1);

namespace App\Ui\Cli;

use App\Domain\ListingRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function date;
use function is_string;
use function sprintf;

final class ListingRaw extends Command
{
    public function __construct(
        private readonly ListingRepository $listingRepository,
    ) {
        parent::__construct('listing:raw');
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'Listing URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        assert(is_string($url));

        $rows = $this->listingRepository->findRawDescriptionsByUrl($url);

        if ($rows === []) {
            $output->writeln('<comment>No listings found for URL.</comment>');

            return 0;
        }

        foreach ($rows as $row) {
            $profileId = (string) $row['watch_profile_id'];
            $storedAt  = (int) $row['stored_at'];
            $output->writeln(sprintf(
                '--- profile: %s, stored: %s ---',
                $profileId,
                date('Y-m-d H:i:s', $storedAt),
            ));
            $output->writeln((string) $row['description']);
            $output->writeln('');
        }

        return 0;
    }
}
