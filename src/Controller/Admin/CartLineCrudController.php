<?php

namespace App\Controller\Admin;

use App\Entity\CartLine;

/**
 * @extends AbstractSecuredCrudController<CartLine>
 */
class CartLineCrudController extends AbstractSecuredCrudController
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
