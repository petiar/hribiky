<?php

namespace App\Command;

use App\Service\BadgeCalculatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:calculate-badges',
    description: 'Prepočíta gamifikačné odznaky (trofeje a úspechy). Musí bežať AŽ PO app:count-mushrooms.',
)]
class CalculateBadgesCommand extends Command
{
    public function __construct(
        private BadgeCalculatorService $badgeCalculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $affected = $this->badgeCalculator->calculateAll();

        $output->writeln(sprintf('Zmenených odznakov: %d', $affected));

        return Command::SUCCESS;
    }
}
