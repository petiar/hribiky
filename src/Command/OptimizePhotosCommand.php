<?php

namespace App\Command;

use App\Entity\Photo;
use App\Service\ImageOptimizer;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jednorazová úprava existujúcich fotiek rovnako ako pri nahrávaní (ImageOptimizer):
 * max. 2000 px, otočenie podľa EXIF, bez metadát.
 *
 * Bez --force nič nezapíše — fotky upraví len do dočasnej kópie a vypíše, koľko by sa ušetrilo.
 * Pred ostrým behom zálohuj public/uploads/photos!
 */
#[AsCommand(
    name: 'app:optimize-photos',
    description: 'Zmenší existujúce fotky na max. 2000 px a odstráni z nich EXIF. Bez --force len nanečisto.',
)]
class OptimizePhotosCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageOptimizer $optimizer,
        private readonly ThumbnailService $thumbnailService,
        #[Autowire('%photos_directory%')]
        private readonly string $uploadDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Naozaj prepíše fotky (bez neho len nanečisto)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Spracuje najviac N fotiek (na vyskúšanie)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $limit = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;

        $paths = $this->em->createQueryBuilder()
            ->select('DISTINCT p.path')
            ->from(Photo::class, 'p')
            ->orderBy('p.path')
            ->getQuery()
            ->getSingleColumnResult();

        $io->title($force ? 'Úprava fotiek' : 'Úprava fotiek — NANEČISTO (bez --force sa nič neprepíše)');
        if ($force) {
            $io->caution('Fotky sa prepíšu. Máš zálohu public/uploads/photos?');
        }

        $stats = ['optimized' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0, 'before' => 0, 'after' => 0];
        $missing = [];
        $failed = [];

        $io->progressStart(count($paths));

        foreach ($paths as $filename) {
            if ($limit !== null && $stats['optimized'] + $stats['failed'] >= $limit) {
                break;
            }

            $path = $this->uploadDir . '/' . basename($filename);

            if (!is_file($path)) {
                $stats['missing']++;
                $missing[] = $filename;
                $io->progressAdvance();
                continue;
            }

            $needs = $this->optimizer->needsOptimization($path);
            if ($needs !== true) {
                // false = už upravená, null = nie je podporovaný obrázok (ostáva nedotknutý)
                $stats['skipped']++;
                $io->progressAdvance();
                continue;
            }

            $before = filesize($path);
            $after = $force ? $this->optimizeInPlace($path) : $this->optimizeCopy($path);

            if ($after === null) {
                $stats['failed']++;
                $failed[] = $filename;
            } else {
                $stats['optimized']++;
                $stats['before'] += $before;
                $stats['after'] += $after;

                // Náhľad pre istotu (ak chýba); existujúce náhľady sú už otočené správne.
                if ($force) {
                    $this->thumbnailService->ensure($filename);
                }
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $saved = $stats['before'] - $stats['after'];
        $io->table(['', 'počet'], [
            [$force ? 'upravené' : 'na úpravu', $stats['optimized']],
            ['preskočené (už v poriadku alebo nie obrázok)', $stats['skipped']],
            ['chýbajúci súbor', $stats['missing']],
            ['chyba pri úprave', $stats['failed']],
        ]);
        $io->writeln(sprintf(
            '%s: %s → %s (%s %s, −%d %%)',
            $force ? 'Upravené fotky' : 'Fotky na úpravu',
            self::mb($stats['before']),
            self::mb($stats['after']),
            $force ? 'ušetrené' : 'ušetrí sa',
            self::mb($saved),
            $stats['before'] > 0 ? round($saved * 100 / $stats['before']) : 0,
        ));

        if ($missing) {
            $io->warning('Súbory v databáze, ktoré na disku chýbajú: ' . implode(', ', array_slice($missing, 0, 20)) . (count($missing) > 20 ? ' …' : ''));
        }
        if ($failed) {
            $io->warning('Nepodarilo sa upraviť (ostali nezmenené): ' . implode(', ', array_slice($failed, 0, 20)) . (count($failed) > 20 ? ' …' : ''));
        }

        if (!$force && $stats['optimized'] > 0) {
            $io->note('Toto bol beh nanečisto. Po zálohe spusti s --force.');
        }

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return int|null nová veľkosť alebo null pri chybe (súbor ostáva nezmenený)
     */
    private function optimizeInPlace(string $path): ?int
    {
        if (!$this->optimizer->optimize($path)) {
            return null;
        }
        clearstatcache(true, $path);

        return filesize($path);
    }

    /**
     * Nanečisto: upraví dočasnú kópiu, zmeria ju a zmaže. Originál sa nezmení.
     */
    private function optimizeCopy(string $path): ?int
    {
        $copy = sys_get_temp_dir() . '/hribiky-dry-' . uniqid() . '-' . basename($path);
        if (!copy($path, $copy)) {
            return null;
        }

        try {
            if (!$this->optimizer->optimize($copy)) {
                return null;
            }
            clearstatcache(true, $copy);

            return filesize($copy);
        } finally {
            @unlink($copy);
        }
    }

    private static function mb(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1, ',', ' ') . ' MB';
    }
}
