<?php

namespace App\Command;

use App\Entity\Mushroom;
use App\Repository\MushroomRepository;
use App\Service\MushroomDescriptionScorerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:score-descriptions',
    description: 'Ohodnotí kvalitu popisov hríbikov cez AI (badge Rozprávač) — len tie s uzavretým edit-oknom.',
)]
class ScoreDescriptionsCommand extends Command
{
    /** Po toľkých neúspešných pokusoch sa hríbik už AI neposiela (šetrí tokeny). */
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private MushroomRepository $mushroomRepository,
        private MushroomDescriptionScorerService $scorer,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dev', null, InputOption::VALUE_NONE, 'Použije heuristiku podľa dĺžky textu namiesto AI (šetrí tokeny)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dev = (bool) $input->getOption('dev');

        $mushrooms = $this->findCandidates();

        if (empty($mushrooms)) {
            $io->info('Žiadne hríbiky na ohodnotenie — všetky majú buď skóre, alebo ešte otvorené edit-okno.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Kandidátov na ohodnotenie: %d', count($mushrooms)));
        if ($dev) {
            $io->writeln('<comment>[DEV] Používam heuristiku podľa dĺžky textu, AI sa nevolá.</comment>');
        }

        foreach ($mushrooms as $mushroom) {
            try {
                $score = $dev
                    ? $this->scorer->scoreDevDescription($mushroom->getDescription())
                    : $this->scorer->scoreDescription($mushroom->getDescription());

                $mushroom->setDescriptionScore($score);
                $mushroom->setDescriptionScoredAt($this->clock->now());
                $this->entityManager->flush();

                $io->writeln(sprintf('%s: skóre %d', $mushroom->getTitle(), $score));
            } catch (\Throwable $e) {
                $io->writeln('<error>CHYBA pri ' . $mushroom->getTitle() . ': ' . $e->getMessage() . '</error>');

                // Ak zlyhala samotná DB, EntityManager je zatvorený — pokus sa nedá zaznamenať.
                if (!$this->entityManager->isOpen()) {
                    throw $e;
                }

                $mushroom->incrementDescriptionScoreAttempts();
                $this->entityManager->flush();

                if ($mushroom->getDescriptionScoreAttempts() >= self::MAX_ATTEMPTS) {
                    $io->writeln(sprintf(
                        '<comment>%s: dosiahnutý limit %d pokusov, ďalej sa nebude hodnotiť.</comment>',
                        $mushroom->getTitle(),
                        self::MAX_ATTEMPTS
                    ));
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return Mushroom[]
     */
    private function findCandidates(): array
    {
        $now = $this->clock->now();
        $fallback = $now->modify('-30 days');

        return $this->mushroomRepository->createQueryBuilder('m')
            ->leftJoin('m.editLink', 'el')
            ->andWhere('m.published = true')
            ->andWhere('m.descriptionScore IS NULL')
            ->andWhere('m.descriptionScoreAttempts < :maxAttempts')
            ->andWhere('m.description IS NOT NULL')
            ->andWhere("m.description <> ''")
            ->andWhere('(el.id IS NULL OR el.usedAt IS NOT NULL OR el.expiresAt < :now OR m.createdAt < :fallback)')
            ->setParameter('now', $now)
            ->setParameter('fallback', $fallback)
            ->setParameter('maxAttempts', self::MAX_ATTEMPTS)
            ->getQuery()
            ->getResult();
    }
}
