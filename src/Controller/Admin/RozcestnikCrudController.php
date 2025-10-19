<?php

namespace App\Controller\Admin;

use App\Entity\Mushroom;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class RozcestnikCrudController extends AbstractCrudController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
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
                break;
            case Crud::PAGE_EDIT:
                $fields[] = NumberField::new('latitude', 'Latitude');
                $fields[] = NumberField::new('longitude', 'Longitude');
                $fields[] = TextareaField::new('description', 'Popis');
                $fields[] = ChoiceField::new('country')
                    ->setChoices([
                        'Slovakia' => 'SK',
                        'Czech Republic' => 'CZ',
                    ])
                    ->renderAsNativeWidget();
                break;
            default:
                $fields[] = TextareaField::new('description', 'Popis');
        }

        $fields[] = TextField::new('title', 'Názov')
        ->formatValue(function ($value, $entity) {
            $url = $this->urlGenerator->generate('mushroom_detail', ['id' => $entity->getId()]);
            return sprintf('<a href="%s">%s</a>', $url, $entity->getTitle());
        })
            ->renderAsHtml();
        $fields[] = TextField::new('name', 'Meno');
        $fields[] = EmailField::new('email', 'Email');
        $fields[] = BooleanField::new('published');
        $fields[] = DateTimeField::new('createdAt', 'Dátum pridania')
            ->setSortable(true);
        return $fields;
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
