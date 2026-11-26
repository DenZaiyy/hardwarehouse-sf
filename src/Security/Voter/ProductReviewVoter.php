<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Repository\OrderRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Avis vérifiés : seul un client dont une commande payée (OrderRepository::PAID_STATUSES) contient le produit peut le
 * noter. Le sujet est l'identifiant du produit dans le catalogue de l'API.
 *
 * @extends Voter<string, string>
 */
final class ProductReviewVoter extends Voter
{
    public const string REVIEW = 'PRODUCT_REVIEW';

    public function __construct(private readonly OrderRepository $orderRepository)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::REVIEW === $attribute && \is_string($subject) && '' !== $subject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->orderRepository->hasBought($user, $subject);
    }
}
