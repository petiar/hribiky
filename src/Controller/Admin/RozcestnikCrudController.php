<?php

namespace App\Controller\Admin;

use App\Entity\Rozcestnik;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RozcestnikCrudController extends AbstractCrudController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Rozcestnik::class;
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
                break;
            case Crud::PAGE_EDIT:
                $fields[] = NumberField::new('latitude', 'Latitude');
                $fields[] = NumberField::new('longitude', 'Longitude');
                $fields[] = TextareaField::new('description', 'Popis');
                break;
            default:
                $fields[] = TextareaField::new('description', 'Popis');
        }

        $fields[] = TextField::new('title', 'Názov')
        ->formatValue(function ($value, $entity) {
            $url = $this->urlGenerator->generate('rozcestnik_detail', ['id' => $entity->getId()]);
            return sprintf('<a href="%s">%s</a>', $url, $entity->getTitle());
        })
            ->renderAsHtml();
        $fields[] = TextField::new('name', 'Meno');
        $fields[] = EmailField::new('email', 'Email');
        $fields[] = BooleanField::new('published');
        $fields[] = DateTimeField::new('created_at', 'Dátum pridania');

        // ImageField::new('fotky')->setBasePath('/uploads')->setUploadDir('public/uploads'),
        return $fields;
    }
}
