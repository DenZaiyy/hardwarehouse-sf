<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

readonly class AuthenticatorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $securityLogger,
        private RequestStack    $requestStack
    ) {
    }

    /** @return array<string> */
    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        ['user_IP' => $userIP] = $this->getRouteNameAndUserIP();

        $request = $event->getRequest();
        $passport = $event->getPassport();
        $exception = $event->getException();

        if (!$passport) {
            return;
        }

        $email = $this->extractUserIdentifier($passport);

        $this->securityLogger->warning(
            "Échec d'authentification pour l'utilisateur '{$email}' depuis l'IP '{$userIP}'. Erreur: {$exception->getMessage()}",
            [
                'user_identifier' => $email,
                'ip_address' => $userIP,
                'user_agent' => $request->headers->get('User-Agent'),
                'exception_class' => $exception::class,
                'timestamp' => $this->getCurrentDate()
            ]
        );
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        [
            "route_name" => $routeName,
            "user_IP" => $userIP
        ] = $this->getRouteNameAndUserIP();

        if (empty($event->getAuthenticatedToken()->getRoleNames())) {
            $this->securityLogger->info("Un utilisateur ayant l'adresse IP : '{$userIP}' est apparu sur la route '{$routeName}'");
        } else {
            $securityToken = $event->getAuthenticatedToken();
            $userEmail = $this->getUserEmail($securityToken);

            $this->securityLogger->info("L'utilisateur '{$userEmail}' s'est connecté le '{$this->getCurrentDate()}' via l'adresse IP '{$userIP}' sur la route '{$routeName}'");
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        /** @var RedirectResponse|null $response */
        $response = $event->getResponse();
        /** @var TokenInterface|null $securityToken */
        $securityToken = $event->getToken();

        if (!$response || !$securityToken) {
            return;
        }

        ['user_IP' => $userIP] = $this->getRouteNameAndUserIP();

        $userEmail = $this->getUserEmail($securityToken);
        $targetUrl = $response->getTargetUrl();

        $this->securityLogger->info("L'utilisateur ayant l'adresse IP '{$userIP}' et l'email '{$userEmail}' s'est déconnecté le '{$this->getCurrentDate()}' et a été redirigé vers l'URL suivante : '{$targetUrl}'");
    }

    private function extractUserIdentifier(Passport $passport): string
    {
        // Depuis le passport (si disponible)
        /** @var UserBadge $userBadge */
        $userBadge = $passport->getBadge(UserBadge::class);
        return $userBadge->getUserIdentifier();
    }

    private function getCurrentDate(): string
    {
        return (new \DateTime('now', new \DateTimeZone('Europe/Paris')))->format('d-m-Y H:i');
    }

    /**
     * Return the user IP and the name of the route where the user has arrived.
     * @return array{user_IP: string|null, route_name: string}
     */
    private function getRouteNameAndUserIP(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return [
                'user_IP' => 'NaN',
                'route_name' => 'NaN'
            ];
        }

        $routeName = $request->attributes->get('_route');

        return [
            'user_IP' => $request->getClientIp() ?? "NaN",
            'route_name' => is_string($routeName) ? $routeName : 'NaN',
        ];
    }

    private function getUserEmail(TokenInterface $securityToken): string
    {
        /** @var User $user */
        $user = $securityToken->getUser();

        return $user->getEmail();
    }
}
