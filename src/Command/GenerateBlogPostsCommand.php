<?php

namespace App\Command;

use App\Entity\Photo;
use App\Repository\BlogPostRepository;
use App\Repository\MushroomRepository;
use App\Service\BlogPostGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-blog-posts',
    description: 'Generuje blogové príspevky z hríbikov pomocou AI a publikuje naplánované príspevky.',
)]
class GenerateBlogPostsCommand extends Command
{
    public function __construct(
        private MushroomRepository $mushroomRepository,
        private BlogPostRepository $blogPostRepository,
        private BlogPostGeneratorService $generator,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->publishScheduled($io);
        $this->generateNew($io);

        return Command::SUCCESS;
    }

    private function publishScheduled(SymfonyStyle $io): void
    {
        $posts = $this->blogPostRepository->findScheduledForPublishing();

        foreach ($posts as $post) {
            $post->setPublished(true);
            $io->writeln(sprintf('Publikujem: %s', $post->getTitle()));
        }

        $this->entityManager->flush();
        $io->success(sprintf('Publikovaných %d naplánovaných príspevkov.', count($posts)));
    }

    private function generateNew(SymfonyStyle $io): void
    {
        $mushroom = $this->mushroomRepository->findOneWithoutBlogPost();

        if (!$mushroom) {
            $io->info('Žiadny hríbik na generovanie — všetky už majú blogpost.');
            return;
        }

        $io->writeln(sprintf('Generujem blogpost pre: %s', $mushroom->getTitle()));

        try {
            $blogPost = $this->generator->generateFromMushroom($mushroom);

            $mushroomPhotos = $mushroom->getPhotos()->toArray();
            $io->writeln(sprintf('Hríbik má %d fotiek.', count($mushroomPhotos)));
            if (!empty($mushroomPhotos)) {
                shuffle($mushroomPhotos);
                $source = $mushroomPhotos[0];
                $photo = new Photo();
                $photo->setPath($source->getPath());
                $photo->setOwner($source->getOwner());
                $blogPost->addPhoto($photo);
                $io->writeln('Fotka priradená: ' . $source->getPath());
            } else {
                $io->writeln('Hríbik nemá žiadne fotky, blogpost bude bez obrázka.');
            }

            $this->entityManager->persist($blogPost);

            $mushroom->setBlogPostGenerated(true);
            $this->entityManager->flush();

            $io->success(sprintf(
                'Vygenerovaný blogpost "%s", bude publikovaný o %s.',
                $blogPost->getTitle(),
                $blogPost->getPublishedAt()->format('H:i')
            ));
        } catch (\Throwable $e) {
            $io->writeln('<error>CHYBA: ' . get_class($e) . '</error>');
            $io->writeln('<error>Správa: ' . $e->getMessage() . '</error>');
            $io->writeln('<error>Súbor: ' . $e->getFile() . ':' . $e->getLine() . '</error>');
            return;
        }
    }
}