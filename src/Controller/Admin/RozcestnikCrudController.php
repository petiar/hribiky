<?php

namespace App\Controller\Admin;

use App\Entity\Mushroom;
use App\Entity\MushroomArticleLink;
use App\Entity\Photo;
use App\Form\MushroomArticleLinkType;
use App\Repository\MushroomRepository;
use App\Service\BlogPostGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class RozcestnikCrudController extends AbstractCrudController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private BlogPostGeneratorService $blogPostGenerator,
        private EntityManagerInterface $entityManager,
        private AdminUrlGenerator $adminUrlGenerator,
        private MushroomRepository $mushroomRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Mushroom::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [];
        switch ($pageName) {
            case Crud::PAGE_INDEX:
                $fields[] = TextareaField::new('description', 'Popis')
                    ->formatValue(function ($value, $entity) {
                        if (strlen($value) > 80) {
                            return substr($value, 0, 80).'…';
                        }

                        return $value;
                    });
                $fields[] = TextField::new('country', 'Krajina');
                $fields[] = BooleanField::new('blogPostGenerated', 'Blog');
                break;
            case Crud::PAGE_EDIT:
                $fields[] = NumberField::new('latitude', 'Latitude')
                    ->setNumDecimals(12);
                $fields[] = NumberField::new('longitude', 'Longitude')
                    ->setNumDecimals(12);
                $fields[] = NumberField::new('altitude', 'Altitude')
                    ->setNumDecimals(12);
                $fields[] = TextareaField::new('description', 'Popis');
                $fields[] = ChoiceField::new('country')
                    ->setChoices([
                        'Slovakia' => 'SK',
                        'Czech Republic' => 'CZ',
                    ])
                    ->renderAsNativeWidget();
                $fields[] = CollectionField::new('articleLinks', 'Články')
                    ->setEntryType(MushroomArticleLinkType::class)
                    ->allowAdd()
                    ->allowDelete()
                    ->setHelp('Interné blogposty alebo externé články súvisiace s týmto hríbikom.');
                break;
            default:
                $fields[] = TextareaField::new('description', 'Popis');
        }

        $fields[] = TextField::new('title', 'Názov')
            ->formatValue(function ($value, $entity) {
                $url = $this->urlGenerator->generate(
                    'mushroom_detail',
                    ['id' => $entity->getId()]
                );

                return sprintf(
                    '<a href="%s">%s</a>',
                    $url,
                    $entity->getTitle()
                );
            })
            ->renderAsHtml();
        $fields[] = TextField::new('name', 'Meno');
        $fields[] = EmailField::new('email', 'Email');
        $fields[] = BooleanField::new('published');
        $fields[] = DateTimeField::new('createdAt', 'Dátum pridania')
            ->setSortable(true);

        return $fields;
    }

    public function configureActions(Actions $actions): Actions
    {
        $generateBlog = Action::new('generateBlog', 'Generovať blog', 'fa fa-robot')
            ->linkToCrudAction('generateBlog')
            ->displayIf(fn(Mushroom $m) => !$m->isBlogPostGenerated());

        return $actions
            ->add(Crud::PAGE_INDEX, $generateBlog)
            ->add(Crud::PAGE_DETAIL, $generateBlog);
    }

    public function generateBlog(AdminContext $context): RedirectResponse
    {
        $id = $context->getRequest()->query->getInt('entityId');
        $mushroom = $this->mushroomRepository->find($id);

        if (!$mushroom) {
            $this->addFlash('danger', 'Hríbik nenájdený.');

            return $this->redirect($this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Crud::PAGE_INDEX)
                ->generateUrl());
        }

        try {
            $blogPost = $this->blogPostGenerator->generateFromMushroom($mushroom);

            $mushroomPhotos = $mushroom->getPhotos()->toArray();
            if (!empty($mushroomPhotos)) {
                shuffle($mushroomPhotos);
                $source = $mushroomPhotos[0];
                $photo = new Photo();
                $photo->setPath($source->getPath());
                $photo->setOwner($source->getOwner());
                $blogPost->addPhoto($photo);
            }

            $this->entityManager->persist($blogPost);

            $articleLink = new MushroomArticleLink();
            $articleLink->setMushroom($mushroom);
            $articleLink->setTitle($blogPost->getTitle());
            $articleLink->setUrl($this->urlGenerator->generate(
                'blog_show',
                ['slug' => $blogPost->getSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ));
            $articleLink->setBlogPost($blogPost);
            $this->entityManager->persist($articleLink);

            $mushroom->setBlogPostGenerated(true);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'Blogpost „%s" bol vygenerovaný. Publikovaný bude o %s.',
                $blogPost->getTitle(),
                $blogPost->getPublishedAt()->format('H:i')
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Chyba pri generovaní: ' . $e->getMessage());
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->syncArticleLinkMushroom($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->syncArticleLinkMushroom($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function syncArticleLinkMushroom(Mushroom $mushroom): void
    {
        foreach ($mushroom->getArticleLinks() as $link) {
            if ($link->getMushroom() !== $mushroom) {
                $link->setMushroom($mushroom);
            }

            if ($link->getBlogPost() !== null) {
                $url = $this->urlGenerator->generate(
                    'blog_show',
                    ['slug' => $link->getBlogPost()->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                $link->setTitle($link->getTitle() ?: $link->getBlogPost()->getTitle());
                $link->setUrl($url);
            }
        }
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                ChoiceFilter::new('country')
                    ->setChoices([
                        'Slovakia' => 'SK',
                        'Czech Republic' => 'CZ',
                    ])
            );
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['createdAt' => 'DESC']);
    }
}