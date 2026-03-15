<?php

namespace App\Controller\Admin;

use App\Entity\BlogPost;
use App\Repository\TagRepository;
use App\Service\FotoUploader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class BlogPostCrudController extends AbstractCrudController
{
    public function __construct(
        private FotoUploader $fotoUploader,
        private TagRepository $tagRepository,
    ) {
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
        yield TextareaField::new('text', 'Text')
            ->hideOnIndex()
            ->setNumOfRows(30)
            ->setHelp('HTML obsah článku. Používaj &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;&lt;li&gt;, &lt;strong&gt;.');

        yield TextField::new('tagsText', 'Tagy')
            ->setHelp('Tagy oddeľuj čiarkou. Nové tagy sa automaticky vytvoria.')
            ->formatValue(fn($v, $entity) => implode(', ', $entity->getTags()->map(fn($t) => $t->getName())->toArray()));

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
        $this->handleTags($entityInstance);
        $this->handleImageUpload($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->handleTags($entityInstance);
        $this->handleImageUpload($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function handleTags(BlogPost $blogPost): void
    {
        $raw = $blogPost->getRawTagsText();

        // Prázdny raw = tagsText nebol odoslaný (Tagify nesynchronizoval).
        // Nechaj existujúce tagy nedotknuté, aby sa nezmazali omylom.
        if ($raw === '') {
            return;
        }

        // Tagify môže poslať JSON array alebo čiarkami oddelený string
        $decoded = json_decode($raw, true);
        $names = is_array($decoded)
            ? array_filter(array_map('trim', array_column($decoded, 'value')))
            : array_filter(array_map('trim', explode(',', $raw)));

        $tags = new ArrayCollection();
        foreach ($names as $name) {
            $tags->add($this->tagRepository->findOrCreate($name));
        }

        $blogPost->setTags($tags);
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

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addCssFile('https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css')
            ->addJsFile('https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js')
            ->addJsFile('js/quill-init.js')
            ->addCssFile('https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css')
            ->addJsFile('https://cdn.jsdelivr.net/npm/@yaireo/tagify')
            ->addJsFile('js/tagify-init.js');
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewOnSite = Action::new('viewOnSite', 'Zobraziť na webe', 'fa fa-eye')
            ->linkToUrl(fn(BlogPost $post) => '/blog/' . $post->getSlug())
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(fn(BlogPost $post) => $post->getSlug() !== '');

        return $actions
            ->add(Crud::PAGE_INDEX, $viewOnSite)
            ->add(Crud::PAGE_DETAIL, $viewOnSite)
            ->add(Crud::PAGE_EDIT, $viewOnSite);
    }
}