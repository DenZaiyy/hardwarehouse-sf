<?php

namespace App\Controller\Admin;

use App\Entity\CartLine;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/**
 * @extends AbstractCrudController<CartLine>
 */
class CartLineCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CartLine::class;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
