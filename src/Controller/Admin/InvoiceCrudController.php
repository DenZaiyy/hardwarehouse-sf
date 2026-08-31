<?php

namespace App\Controller\Admin;

use App\Entity\Invoice;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractSecuredCrudController<Invoice>
 */
class InvoiceCrudController extends AbstractSecuredCrudController
{
    public static function getEntityFqcn(): string
    {
        return Invoice::class;
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
