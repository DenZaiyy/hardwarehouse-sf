<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractSecuredCrudController<Order>
 */
class OrderCrudController extends AbstractSecuredCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['id' => 'DESC']);
    }

    /**
     * Une commande naît du tunnel et sa facture est une pièce comptable : ni création ni suppression
     * ici, seul le statut se modifie.
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::DELETE);
    }

    /** Statut et moyen de paiement sont des enums : seul un ChoiceField sait les afficher. */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('reference', 'Référence')->hideOnForm();
        yield DateTimeField::new('created_at', 'Date')->hideOnForm();
        yield TextField::new('userFullNameSnapshot', 'Client')->hideOnForm();
        yield EmailField::new('customerEmail', 'E-mail')->hideOnForm();
        yield ChoiceField::new('status', 'Statut')->renderAsBadges([
            'PENDING' => 'secondary',
            'CONFIRMED' => 'primary',
            'PROCESSING' => 'info',
            'SHIPPED' => 'warning',
            'DELIVERED' => 'success',
            'CANCELLED' => 'danger',
        ]);
        yield ChoiceField::new('paymentMethod', 'Paiement')->hideOnForm();
        yield MoneyField::new('totalAmount', 'Total TTC')->setCurrency('EUR')->setStoredAsCents(false)->hideOnForm();
    }
}
