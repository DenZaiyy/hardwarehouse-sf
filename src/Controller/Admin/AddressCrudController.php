<?php

namespace App\Controller\Admin;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\AddressType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Address>
 */
class AddressCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Address::class;
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user')->autocomplete())
            ->add(ChoiceFilter::new('type')->setChoices([
                'Delivery' => 'DELIVERY',
                'Billing' => 'BILLING',
            ]));
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID'),
            AssociationField::new('user')
                ->formatValue(static fn (User $user) => $user->getUsername())
                ->setSortProperty('username'),
            ChoiceField::new('type')->setChoices([
                'Delivery' => 'DELIVERY',
                'Billing' => 'BILLING',
            ]),
            TextField::new('label', 'Intitulé'),
            TextField::new('firstname', 'Prénom'),
            TextField::new('lastname', 'Nom'),
            TextField::new('address', 'Adresse'),
            TextField::new('city', 'Ville'),
            TextField::new('postalCode', 'Code postal'),
        ];
    }
}
