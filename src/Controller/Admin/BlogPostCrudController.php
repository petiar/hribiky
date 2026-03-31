<?php

namespace App\Controller\Admin;

use App\Entity\BlogPost;
use App\Entity\Photo;
use App\Repository\TagRepository;
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

class BlogPostCrudController extends AbstractCrudController
{
    public function __construct(
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
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->handleTags($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
        $this->linkPendingPhotos($entityInstance, $entityManager);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->handleTags($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function linkPendingPhotos(BlogPost $blogPost, EntityManagerInterface $em): void
    {
        $ids = $this->getContext()->getRequest()->request->all()['BlogPost']['pendingPhotoIds'] ?? [];
        foreach (array_filter((array) $ids) as $id) {
            $photo = $em->find(Photo::class, (int) $id);
            if ($photo && $photo->getBlogPost() === null) {
                $photo->setBlogPost($blogPost);
            }
        }
        $em->flush();
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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addHtmlContentToHead('<style>
                .tiptap-wrapper { border: 1px solid #ced4da; border-radius: 4px; overflow: hidden; background: #fff; }
                .tiptap-toolbar { display: flex; flex-wrap: wrap; gap: 2px; padding: 6px 8px; border-bottom: 1px solid #ced4da; background: #f8f9fa; }
                .tiptap-btn { background: none; border: 1px solid transparent; border-radius: 3px; padding: 3px 8px; cursor: pointer; font-size: 13px; color: #333; }
                .tiptap-btn:hover { background: #e9ecef; border-color: #ced4da; }
                .tiptap-btn.is-active { background: #0d6efd; color: #fff; border-color: #0d6efd; }
                .tiptap-content { min-height: 400px; padding: 12px 16px; outline: none; font-size: 14px; line-height: 1.6; }
                .tiptap-content:focus-within { box-shadow: inset 0 0 0 2px #86b7fe; }
                .tiptap-content .ProseMirror { min-height: 380px; outline: none; }
                .tiptap-content h2 { font-size: 1.4em; margin: 1em 0 .4em; }
                .tiptap-content h3 { font-size: 1.2em; margin: 1em 0 .4em; }
                .tiptap-content blockquote { border-left: 3px solid #ced4da; margin: 0; padding-left: 1em; color: #666; }
                .tiptap-content a { color: #0d6efd; }
                .tiptap-content ul, .tiptap-content ol { padding-left: 1.5em; }
                .tiptap-content hr { border: none; border-top: 2px solid #ced4da; margin: 1em 0; }
                .ProseMirror p.is-editor-empty:first-child::before { content: attr(data-placeholder); color: #aaa; pointer-events: none; height: 0; float: left; }
                .tiptap-content img { max-width: 100%; height: auto; border-radius: 4px; cursor: pointer; }
                .tiptap-content img.ProseMirror-selectednode { outline: 2px solid #0d6efd; }
            </style>')
            ->addHtmlContentToBody('<script type="module" src="/js/tiptap-init.js"></script>')
            ->addHtmlContentToHead('<style>
                .blog-photo-section { max-width: 900px; margin: 24px auto; padding: 0 16px; }
                .blog-photo-title { font-size: 15px; font-weight: 600; margin-bottom: 10px; color: #333; }
                .blog-photo-dropzone { display: flex; align-items: center; justify-content: center; border: 2px dashed #ced4da; border-radius: 6px; padding: 28px; cursor: pointer; text-align: center; transition: border-color .2s, background .2s; }
                .blog-photo-dropzone:hover, .blog-photo-dropzone.drag-over { border-color: #0d6efd; background: #f0f6ff; }
                .blog-photo-dropzone-text { color: #555; line-height: 1.6; pointer-events: none; }
                .blog-photo-progress { height: 6px; background: #e9ecef; border-radius: 3px; margin: 10px 0; overflow: hidden; }
                .blog-photo-progress-bar { height: 100%; background: #0d6efd; border-radius: 3px; transition: width .2s; }
                .blog-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-top: 16px; }
                .blog-photo-card { border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; background: #fff; }
                .blog-photo-card img { width: 100%; height: 130px; object-fit: cover; display: block; }
                .blog-photo-card-url { display: flex; align-items: center; gap: 4px; padding: 6px; background: #f8f9fa; }
                .blog-photo-card-url input { flex: 1; min-width: 0; font-size: 11px; border: 1px solid #ced4da; border-radius: 3px; padding: 2px 5px; background: #fff; color: #333; }
                .blog-photo-copy-btn { background: none; border: none; cursor: pointer; font-size: 14px; padding: 0 2px; flex-shrink: 0; }
            </style>')
            ->addHtmlContentToBody('<script src="/js/blog-photo-upload.js" defer></script>')
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