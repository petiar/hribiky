<?php
// src/Controller/Admin/UserCrudController.php
namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class UserCrudController extends AbstractCrudController
{
    // Roly, ktoré chceš spravovať cez admin
    private const ROLE_CHOICES = [
        // 'User'      => 'ROLE_USER',
        'Reliable'  => 'ROLE_RELIABLE',
        'Admin'     => 'ROLE_ADMIN',
    ];

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        $id    = IdField::new('id')->onlyOnIndex();
        $name  = TextField::new('name', 'Name');
        $email = EmailField::new('email', 'Email');

        $rolesIndex = ChoiceField::new('roles', 'Roles')
            ->setChoices(self::ROLE_CHOICES)   // label => value
            ->allowMultipleChoices(true)
            ->renderAsBadges([
                'ROLE_USER'     => 'secondary',
                'ROLE_RELIABLE' => 'warning',
                'ROLE_ADMIN'    => 'primary',
            ])
            ->onlyOnIndex();

        $rolesForm = ChoiceField::new('roles', 'Roles')
            ->setChoices(self::ROLE_CHOICES)
            ->allowMultipleChoices(true)
            ->autocomplete();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $name, $email, $rolesIndex];
        }

        return [$name, $email, $rolesForm];
    }
}
