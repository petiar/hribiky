<?php

namespace App\Controller\Admin;

use App\Entity\BlogPost;
use App\Service\FotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class BlogPostCrudController extends AbstractCrudController
{
    public function __construct(private FotoUploader $fotoUploader)
    {
    }

    public static function getEntityFqcn(): string
    {
        return BlogPost::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Názov');
        yield TextField::new('slug', 'Slug (URL)')
            ->hideOnIndex()
            ->setHelp('Automaticky sa vygeneruje z názvu. Môžeš upraviť.');
        yield TextareaField::new('shortDescription', 'Krátky popis')
            ->hideOnIndex();
        yield TextEditorField::new('text', 'Text')
            ->hideOnIndex();
        yield ArrayField::new('tags', 'Tagy');
        yield BooleanField::new('published', 'Publikovaný');
        yield DateTimeField::new('publishedAt', 'Dátum publikácie');
        yield DateTimeField::new('createdAt', 'Vytvorený')
            ->hideOnForm();
        yield Field::new('imageUpload', 'Obrázky')
            ->setFormType(FileType::class)
            ->setFormTypeOptions([
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
            ])
            ->hideOnIndex()
            ->hideOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->handleImageUpload($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->handleImageUpload($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function handleImageUpload(BlogPost $blogPost): void
    {
        $files = $this->getContext()->getRequest()->files->get('BlogPost')['imageUpload'] ?? [];

        if (!empty($files)) {
            $this->fotoUploader->uploadAndAttach($files, $blogPost);
        }
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewOnSite = Action::new('viewOnSite', 'Zobraziť na webe', 'fa fa-eye')
            ->linkToUrl(fn(BlogPost $post) => '/blog/' . $post->getSlug())
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(fn(BlogPost $post) => $post->getSlug() !== null);

        return $actions
            ->add(Crud::PAGE_INDEX, $viewOnSite)
            ->add(Crud::PAGE_DETAIL, $viewOnSite)
            ->add(Crud::PAGE_EDIT, $viewOnSite);
    }
}