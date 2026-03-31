<?php

namespace App\Command;

use App\Entity\Photo;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-thumbnails',
    description: 'Vygeneruje náhľady (thumbs) pre všetky fotky, ktoré ich ešte nemajú.',
)]
class GenerateThumbnailsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ThumbnailService $thumbnailService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $photos = $this->em->getRepository(Photo::class)->findAll();
        $total = count($photos);
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        $io->progressStart($total);

        foreach ($photos as $photo) {
            $filename = $photo->getPath();

            if ($this->thumbnailService->thumbExists($filename)) {
                $skipped++;
            } elseif ($this->thumbnailService->generate($filename)) {
                $generated++;
            } else {
                $failed++;
                $io->warning("Nepodarilo sa: {$filename}");
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success("Hotovo. Vygenerovaných: {$generated}, preskočených: {$skipped}, chýb: {$failed}.");

        return Command::SUCCESS;
    }
}