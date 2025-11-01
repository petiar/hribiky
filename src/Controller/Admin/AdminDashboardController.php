<?php

namespace App\Controller\Admin;

use App\Entity\AccessLog;
use App\Entity\Blacklist;
use App\Entity\Feedback;
use App\Entity\Mushroom;
use App\Entity\MushroomComment;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Dto\LocaleDto;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class AdminDashboardController extends AbstractDashboardController
{

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
        ]);

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // 1.1) If you have enabled the "pretty URLs" feature:
        // return $this->redirectToRoute('admin_user_index');
        //
        // 1.2) Same example but using the "ugly URLs" that were used in previous EasyAdmin versions:
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Hríbiky')
            ->setFaviconPath('favicon.svg')

            // the domain used by default is 'messages'
            ->setTranslationDomain('my-custom-domain')

            // there's no need to define the "text direction" explicitly because
            // its default value is inferred dynamically from the user locale
            ->setTextDirection('ltr')

            // set this option if you prefer the page content to span the entire
            // browser width, instead of the default design which sets a max width
            ->renderContentMaximized()

            // set this option if you prefer the sidebar (which contains the main menu)
            // to be displayed as a narrow column instead of the default expanded design
            ->renderSidebarMinimized()

            // by default, users can select between a "light" and "dark" mode for the
            // backend interface. Call this method if you prefer to disable the "dark"
            // mode for any reason (e.g. if your interface customizations are not ready for it)
            ->disableDarkMode()

            // by default, the UI color scheme is 'auto', which means that the backend
            // will use the same mode (light/dark) as the operating system and will
            // change in sync when the OS mode changes.
            // Use this option to set which mode ('light', 'dark' or 'auto') will users see
            // by default in the backend (users can change it via the color scheme selector)
            ->setDefaultColorScheme('dark')
            // instead of magic strings, you can use constants as the value of
            // this option: EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme::DARK

            // by default, all backend URLs are generated as absolute URLs. If you
            // need to generate relative URLs instead, call this method
            ->generateRelativeUrls()

            // set this option if you want to enable locale switching in dashboard.
            // IMPORTANT: this feature won't work unless you add the {_locale}
            // parameter in the admin dashboard URL (e.g. '/admin/{_locale}').
            // the name of each locale will be rendered in that locale
            // (in the following example you'll see: "English", "Polski")
            ->setLocales(['en', 'sk'])
            // to customize the labels of locales, pass a key => value array
            // (e.g. to display flags; although it's not a recommended practice,
            // because many languages/locales are not associated to a single country)
            ->setLocales([
                'en' => 'English',
                'sk' => 'Slovensky',
            ]);
    }

    public function configureMenuItems(): iterable
    {
        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-home'),
            MenuItem::linkToCrud('Hríbiky', 'fa fa-tags', Mushroom::class),
            MenuItem::linkToCrud(
                'Aktualizácie',
                'fa fa-file-text',
                MushroomComment::class
            ),
            MenuItem::linkToCrud('Feedback', 'fa fa-comment', Feedback::class),
            MenuItem::linkToCrud(
                'Access Log',
                'fa fa-history',
                AccessLog::class
            ),
            MenuItem::linkToCrud('Blacklist', 'fa fa-stop', Blacklist::class),
            MenuItem::linkToCrud('Užívatelia', 'fa fa-user', User::class),
        ];
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        return parent::configureUserMenu($user)
            // use the given $user object to get the user name
            ->setName($user->getEmail())
            // use this method if you don't want to display the name of the user
            ->displayUserName(false)

            // use this method if you don't want to display the user image
            ->displayUserAvatar(false)
            // you can also pass an email address to use gravatar's service
            ->setGravatarEmail($user->getEmail())

            // you can use any type of menu item, except submenus
            ->addMenuItems([
                MenuItem::linkToRoute(
                    'My Profile',
                    'fa fa-id-card',
                    '...',
                    ['...' => '...']
                ),
                MenuItem::linkToRoute(
                    'Settings',
                    'fa fa-user-cog',
                    '...',
                    ['...' => '...']
                ),
                MenuItem::section(),
                MenuItem::linkToLogout('Logout', 'fa fa-sign-out'),
            ]);
    }
}
