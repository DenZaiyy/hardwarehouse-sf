<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Rating;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Modération des avis vérifiés : l'administrateur les lit et supprime un avis abusif. Il ne les écrit ni ne les
 * modifie, un avis reste la parole de son auteur.
 *
 * @extends AbstractSecuredCrudController<Rating>
 */
class RatingCrudController extends AbstractSecuredCrudController
{
    public static function getEntityFqcn(): string
    {
        return Rating::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Review')
            ->setEntityLabelInPlural('Reviews')
            ->setDefaultSort(['created_at' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('productId', 'Product');
        yield AssociationField::new('user', 'Author');
        yield NumberField::new('note', 'Rating')->setNumDecimals(0);
        yield TextareaField::new('comment', 'Comment')->setMaxLength(Crud::PAGE_INDEX === $pageName ? 80 : 1000);
        yield DateTimeField::new('created_at', 'Published');
        yield DateTimeField::new('updated_at', 'Edited');
    }
}
