<?php

namespace App\Command;

use App\Entity\BlogPost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-blog-html',
    description: 'Vyčistí HTML článkov (&nbsp;, prázdne odrážky) — BlogHtml::clean. Spúšťa ho deploy, opakovaný beh nič nezmení.',
)]
class CleanBlogHtmlCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $changed = 0;
        foreach ($this->em->getRepository(BlogPost::class)->findAll() as $post) {
            $before = $post->getText();
            $post->setText($before); // setText čistí
            if ($post->getText() !== $before) {
                $changed++;
                $io->writeln(sprintf('Vyčistený: %s', $post->getSlug()), SymfonyStyle::VERBOSITY_VERBOSE);
            }
        }
        $this->em->flush();

        $io->success("Hotovo. Vyčistených článkov: {$changed}.");

        return Command::SUCCESS;
    }
}
