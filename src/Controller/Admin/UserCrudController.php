<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\UserRoleType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AvatarField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<User>
 */
class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

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

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $rolesField = ChoiceField::new('roles', 'Rôles')
            ->setChoices(UserRoleType::cases())
            ->formatValue(fn (array $values): string => implode(', ', array_map(
                function (mixed $role): string {
                    assert(is_string($role));

                    return UserRoleType::from($role)->trans($this->translator);
                },
                $values
            )))
            ->allowMultipleChoices()
            ->renderExpanded();

        if (Action::NEW === $pageName) {
            $rolesField->setFormTypeOption('data', [UserRoleType::USER->value]);
        }

        return [
            FormField::addTab('Information de base'),
            IdField::new('id')->onlyOnIndex(),
            TextField::new('username'),
            TextField::new('email'),

            FormField::addTab('Rôles'),
            $rolesField,

            FormField::addTab('Avatar'),
            AvatarField::new('avatar', 'Avatar')
                ->formatValue(fn (?string $avatar) => $avatar ? '/uploads/images/avatar/'.$avatar : '')->setHeight(50),

            FormField::addTab('Status'),
            BooleanField::new('is_banned')->renderAsSwitch(),
            BooleanField::new('is_verified'),

            FormField::addTab('Dates'),
            DateTimeField::new('created_at')->setTimezone('Europe/Paris')->setColumns(6),
            DateTimeField::new('updated_at')->setTimezone('Europe/Paris')->setColumns(6),
        ];
    }
}
