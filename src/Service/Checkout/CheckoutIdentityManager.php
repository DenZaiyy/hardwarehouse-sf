<?php

namespace App\Service\Checkout;

use App\DTO\Checkout\CheckoutState;
use App\DTO\Checkout\GuestIdentityData;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CheckoutIdentityManager
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function syncAuthenticatedUser(CheckoutState $state): CheckoutState
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return $state;
        }

        $state->identityMode = 'authenticated';
        $state->identity = [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ];
        $state->identityCompleted = true;

        if ($state->currentStep < 2) {
            $state->currentStep = 2;
        }

        return $state;
    }

    public function saveGuestIdentity(CheckoutState $state, GuestIdentityData $data): CheckoutState
    {
        $state->identityMode = 'guest';
        $state->identity = [
            'title' => $data->title,
            'firstName' => $data->firstName,
            'lastName' => $data->lastName,
            'email' => $data->email,
        ];
        $state->identityCompleted = true;
        $state->currentStep = 2;

        return $state;
    }
}
