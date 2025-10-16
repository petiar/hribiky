<?php

namespace App\Controller\Admin;

use App\Entity\AccessLog;
use App\Entity\Blacklist;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AccessLogCrudController extends AbstractCrudController
{
    public function __construct(private AdminUrlGenerator $adminUrlGenerator, private EntityManagerInterface $entityManager) {}

    public static function getEntityFqcn(): string
    {
        return AccessLog::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('ipAddress', 'IP adresa')
                ->formatValue(function ($value, AccessLog $log) {
                    $repo = $this->entityManager->getRepository(Blacklist::class);
                    $isBlocked = $repo->findOneBy(['ipAddress' => $value]) !== null;

                    // URL na BlacklistCrudController -> create
                    $url = $this->adminUrlGenerator
                        ->setController(\App\Controller\Admin\BlacklistCrudController::class)
                        ->setAction(Crud::PAGE_NEW)
                        ->set('ip', $value) // pridáme parameter
                        ->generateUrl();

                    if ($isBlocked) {
                        return sprintf(
                            '<a href="%s" style="text-decoration: line-through; color: red;" title="Zablokovaná IP">%s</a>',
                            $url,
                            htmlspecialchars($value)
                        );
                    }

                    return sprintf(
                        '<a href="%s" title="Zablokovať IP">%s</a>',
                        $url,
                        htmlspecialchars($value)
                    );
                })
                ->renderAsHtml(true),


            TextField::new('entityClass', 'Entita')->onlyOnIndex(),
            NumberField::new('entityId', 'Entity ID')->onlyOnIndex(),
            DateTimeField::new('accessedAt', 'Čas prístupu'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $blockIp = Action::new('blockIp', 'Zablokovať IP')
            ->linkToCrudAction('blockIp');

        return $actions
            ->add(Crud::PAGE_INDEX, $blockIp);
    }

    public function blockIp(AdminUrlGenerator $urlGenerator, EntityManagerInterface $em, Request $request): Response
    {
        $id = $request->query->get('entityId');
        $log = $em->getRepository(AccessLog::class)->find($id);

        if (!$log) {
            throw $this->createNotFoundException();
        }

        $ip = $log->getIpAddress();

        // jednoduché potvrdenie
        if ($request->isMethod('POST')) {
            $existing = $em->getRepository(Blacklist::class)->findOneBy(['ipAddress' => $ip]);
            if (!$existing) {
                $em->persist(new Blacklist($ip));
                $em->flush();
            }

            $url = $urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
            return $this->redirect($url);
        }

        return new Response(<<<HTML
            <h2>Blokovať IP: $ip</h2>
            <form method="POST">
                <button type="submit">Potvrdiť blokovanie</button>
                <a href="{$this->adminUrlGenerator->setAction(Action::INDEX)->generateUrl()}">Zrušiť</a>
            </form>
        HTML);
    }
}
