<?php

namespace App\Controller\Admin;

use App\Entity\User;
use DateTimeZone;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<User>
 */
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('email')
            ->add('roles')
            ->add('is_verified')
            ->add('is_banned');
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addTab('Information de base'),
            IdField::new('id')->onlyOnIndex(),
            TextField::new('username'),
            TextField::new('email'),

            FormField::addTab('Rôles'),
            ChoiceField::new('roles')->setChoices([
                'Super Admin' => 'ROLE_SUPER_ADMIN',
                'Administrateur' => 'ROLE_ADMIN',
                'Utilisateur' => 'ROLE_USER',
            ])->allowMultipleChoices(),

            FormField::addTab('Avatar'),
            AvatarField::new('avatar', 'Avatar')
                ->formatValue(function (string $avatar) {
                    return '/uploads/images/avatar/' . $avatar;
                })->setHeight(50),

            FormField::addTab('Status'),
            BooleanField::new('is_banned')->renderAsSwitch(),
            BooleanField::new('is_verified'),

            FormField::addTab('Dates'),
            DateTimeField::new('created_at')->setTimezone('Europe/Paris')->setColumns(6),
            DateTimeField::new('updated_at')->setTimezone('Europe/Paris')->setColumns(6),
        ];
    }
}
