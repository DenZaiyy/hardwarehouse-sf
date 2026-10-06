<?php

namespace App\Controller\Admin;

use App\Entity\Shipment;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractSecuredCrudController<Shipment>
 */
class ShipmentCrudController extends AbstractSecuredCrudController
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
            // Choix déduits de l'enumType du mapping
            ChoiceField::new('status', 'Statut'),
            DateTimeField::new('expedition_date', 'Date d\'expédition'),
            DateTimeField::new('delivery_date', 'Date de livraison'),
            TextField::new('tracking_number', 'Numéro de suivi'),
            DateTimeField::new('created_at', 'Créé le')
                ->hideOnForm(),
            DateTimeField::new('updated_at', 'Mise à jour le')
                ->hideOnForm(),
            AssociationField::new('carrier', 'Transporteur')
                ->onlyOnIndex()
                // La valeur reçue est le transporteur lui-même, pas une chaîne
                ->formatValue(static fn (mixed $value, Shipment $entity): string => $entity->getCarrier()?->getName() ?? '-'),
        ];
    }
}
