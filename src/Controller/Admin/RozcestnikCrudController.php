<?php

namespace App\Controller\Admin;

use App\Entity\Rozcestnik;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RozcestnikCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Rozcestnik::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title', 'Názov'),
            TextField::new('name', 'Meno'),
            EmailField::new('email', 'Email'),
            BooleanField::new('published'),
            DateTimeField::new('created_at', 'Dátum pridania'),
            // ImageField::new('fotky')->setBasePath('/uploads')->setUploadDir('public/uploads'),
        ];
    }
}
