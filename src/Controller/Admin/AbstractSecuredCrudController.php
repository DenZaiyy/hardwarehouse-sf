<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Base class for every admin CRUD controller.
 *
 * `security.yaml`'s `access_control` cannot reliably protect these routes: they are
 * prefixed by `{_locale}` (`/fr/admin/...`, `/en/admin/...`), so a plain `^/admin` rule
 * never matches. Authorization is therefore enforced here, colocated with the code it
 * protects, so a new CRUD controller is secured by construction instead of depending on
 * someone remembering to update a central regex.
 *
 * @template TEntity of object
 *
 * @extends AbstractCrudController<TEntity>
 */
#[IsGranted('ROLE_ADMIN')]
abstract class AbstractSecuredCrudController extends AbstractCrudController
{
}
