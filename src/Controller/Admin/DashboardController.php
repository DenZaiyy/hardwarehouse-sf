<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas accès à cette section.')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        //return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // return $this->redirectToRoute('admin_carrier_index');

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('HardWareHouse')
        ;
    }

    public function configureMenuItems(): iterable
    {
        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-home'),

            MenuItem::section('Users'),
            MenuItem::linkTo(UserCrudController::class, 'Users', 'fa-solid fa-users'),
            MenuItem::linkTo(AddressCrudController::class, 'Address', 'fa-solid fa-address-book'),

            MenuItem::section('Orders'),
            MenuItem::linkTo(OrderCrudController::class, 'Orders', 'fa-solid fa-cart-shopping'),
            MenuItem::linkTo(OrderLineCrudController::class, 'Order line', 'fa-solid fa-shopping-cart'),
            MenuItem::linkTo(InvoiceCrudController::class, 'Invoice', 'fa-solid fa-file-invoice'),

            MenuItem::section('Shipments'),
            MenuItem::linkTo(CarrierCrudController::class, 'Carriers', 'fa-solid fa-truck'),
            MenuItem::linkTo(ShipmentCrudController::class, 'Shipments', 'fa-solid fa-box'),


        ];
    }
}
