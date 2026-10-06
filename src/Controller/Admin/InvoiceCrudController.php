<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

/**
 * @extends AbstractSecuredCrudController<Invoice>
 */
class InvoiceCrudController extends AbstractSecuredCrudController
{
    public static function getEntityFqcn(): string
    {
        return Invoice::class;
    }

    /** Pièces comptables émises automatiquement au paiement : consultables, jamais créées ni modifiées ici. */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }
}
