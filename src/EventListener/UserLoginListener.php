<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\CartService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class, method: 'onLoginSuccess')]
class UserLoginListener
{
    public function __construct(
        private readonly CartService $cartService,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($user instanceof User) {
            $this->cartService->associateCartToUser($user);
        }
    }
}
