<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Address;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un client ne gère que ses propres adresses.
 *
 * @extends Voter<string, Address>
 */
final class AddressVoter extends Voter
{
    public const string MANAGE = 'ADDRESS_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof Address;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User
            && null !== $user->getId()
            && $subject->getUser()?->getId() === $user->getId();
    }
}
