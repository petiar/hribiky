<?php

namespace App\Command;

use App\Entity\Mushroom;
use App\Service\Geo\LocationResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:resolve-locations',
    description: 'Doplní hríbikom obec, okres, kraj (Nominatim) a pohorie. Spúšťa ho deploy a cron.',
)]
class ResolveLocationsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocationResolver $resolver,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Spracovať znova všetky hríbiky, nielen tie bez polohy')]
        bool $all = false,
        #[Option('Najviac toľko hríbikov (0 = bez obmedzenia)')]
        int $limit = 0,
        #[Option('Pauza medzi požiadavkami v ms — Nominatim povoľuje najviac 1 za sekundu')]
        int $delay = 1100,
    ): int {
        $qb = $this->em->getRepository(Mushroom::class)->createQueryBuilder('m')->orderBy('m.id', 'DESC');
        if (!$all) {
            $qb->where('m.locationResolvedAt IS NULL');
        }
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        $mushrooms = $qb->getQuery()->getResult();

        $done = 0;
        $failed = 0;
        foreach ($mushrooms as $i => $mushroom) {
            if ($i > 0 && $delay > 0) {
                usleep($delay * 1000);
            }
            try {
                $this->resolver->resolve($mushroom);
                $this->em->flush();
                $done++;
                $io->writeln(sprintf('#%d %s: %s | %s', $mushroom->getId(), $mushroom->getTitle(),
                    implode(', ', array_filter([$mushroom->getLocality(), $mushroom->getDistrict(), $mushroom->getRegion()])) ?: '–',
                    $mushroom->getMountainRange() ?? '–'), SymfonyStyle::VERBOSITY_VERBOSE);
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->warning('Polohu hríbika sa nepodarilo zistiť', ['id' => $mushroom->getId(), 'error' => $e->getMessage()]);
                $io->warning(sprintf('#%d: %s', $mushroom->getId(), $e->getMessage()));
                if ($failed >= 5 && $done === 0) {
                    $io->error('Služba nefunguje, končím. Zvyšok sa doplní pri ďalšom behu.');

                    return Command::FAILURE;
                }
            }
        }

        $io->success("Hotovo. Doplnených: {$done}, chýb: {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
