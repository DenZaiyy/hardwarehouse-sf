<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 40)]
final readonly class LocaleRedirectListener
{
    public function __construct(
        private RouterInterface $router,
        private string $defaultLocale = 'fr',
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== '/') {
            return;
        }

        $locale = $request->getPreferredLanguage(['fr', 'en']) ?? $this->defaultLocale;

        // Force locale into routing context
        $this->router->getContext()->setParameter('_locale', $locale);

        $url = $this->router->generate('homepage');

        $event->setResponse(new RedirectResponse($url, 302));
    }
}
