<?php

namespace App\Controller\Admin;

use App\Entity\Shipment;
use App\Enum\ShipmentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Shipment>
 */
class ShipmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Shipment::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->hideOnForm(),
            ChoiceField::new('status', 'Statut')->setChoices([ShipmentStatus::class]),
            DateTimeField::new('expedition_date', 'Date d\'expédition'),
            DateTimeField::new('delivery_date', 'Date de livraison'),
            TextField::new('tracking_number', 'Numéro de suivi'),
            DateTimeField::new('created_at', 'Créé le')
                ->hideOnForm(),
            DateTimeField::new('updated_at', 'Mise à jour le')
                ->hideOnForm(),
            AssociationField::new('carrier', 'Transporteur')
                ->onlyOnIndex()
                ->formatValue(static fn (?string $value, Shipment $entity): string => $entity->getCarrier()?->getName() ?? '-'),
        ];
    }
}
