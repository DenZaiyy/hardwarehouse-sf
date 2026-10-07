<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use App\Service\Checkout\CheckoutStateManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * La confirmation d'une commande n'est visible que par son client : le titulaire du compte,
 * ou, pour un achat sans compte, la session dans laquelle la commande a été passée. Le détail d'une
 * commande dans l'espace client est réservé au titulaire du compte.
 *
 * @extends Voter<string, Order>
 */
final class OrderVoter extends Voter
{
    public const string VIEW_CONFIRMATION = 'ORDER_VIEW_CONFIRMATION';
    public const string VIEW = 'ORDER_VIEW';

    public function __construct(
        private readonly CheckoutStateManager $checkoutStateManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW_CONFIRMATION, self::VIEW], true) && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $customer = $subject->getUser();

        if (null === $customer && self::VIEW_CONFIRMATION === $attribute) {
            return null !== $subject->getReference()
                && $this->checkoutStateManager->remembersOrderReference($subject->getReference());
        }

        $user = $token->getUser();

        return null !== $customer && $user instanceof User && $customer->getId() === $user->getId();
    }
}
