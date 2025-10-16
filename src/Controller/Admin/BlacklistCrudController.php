<?php

namespace App\Controller\Admin;

use App\Entity\Blacklist;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BlacklistCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Blacklist::class;
    }

    public function createEntity(string $entityFqcn)
    {
        $request = $this->getContext()->getRequest();
        $ip = $request->query->get('ip');

        $blacklist = new Blacklist();
        if ($ip) {
            $blacklist->setIpAddress($ip);
        }

        return $blacklist;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
