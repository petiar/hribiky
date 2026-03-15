<?php

namespace App\Controller\Admin;

use App\Entity\MushroomArticleLink;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class MushroomArticleLinkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MushroomArticleLink::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('mushroom', 'Hríbik')
            ->setCrudController(RozcestnikCrudController::class)
            ->autocomplete()
            ->setHelp('Začni písať názov hríbika na vyhľadanie.');

        yield TextField::new('title', 'Názov článku')
            ->setHelp('Titulok článku, ktorý sa zobrazí na stránke hríbika.');

        yield UrlField::new('url', 'URL článku')
            ->setHelp('Interný odkaz (napr. /blog/nazov-clanku) alebo externá URL.');

        yield AssociationField::new('blogPost', 'Blogpost (interný)')
            ->setCrudController(BlogPostCrudController::class)
            ->autocomplete()
            ->setRequired(false)
            ->setHelp('Vyplň len ak ide o článok z nášho blogu. Pri externých článkoch nechaj prázdne.');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Odkaz na článok')
            ->setEntityLabelInPlural('Odkazy na články')
            ->setDefaultSort(['id' => 'DESC']);
    }
}