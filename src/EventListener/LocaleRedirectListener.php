<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 33)]
final readonly class LocaleRedirectListener
{
    private const array LOCALES = ['fr', 'en'];
    private const string DEFAULT_LOCALE = 'fr';

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Ignorer tout ce qui commence par underscore (profiler, wdt, etc.)
        if (str_starts_with($path, '/_') || str_starts_with($path, '/sitemap')) {
            return;
        }

        // Racine : rediriger vers la locale
        if ('/' === $path) {
            $locale = $request->getPreferredLanguage(self::LOCALES) ?? self::DEFAULT_LOCALE;
            $event->setResponse(new RedirectResponse('/'.$locale, 302));

            return;
        }

        $segments = explode('/', ltrim($path, '/'));
        $firstSegment = $segments[0];

        // Locale déjà présente
        if (in_array($firstSegment, self::LOCALES, true)) {
            return;
        }

        // Redirection
        $locale = $request->getPreferredLanguage(self::LOCALES) ?? self::DEFAULT_LOCALE;
        $queryString = $request->getQueryString();
        $redirectUrl = '/'.$locale.$path;

        if ($queryString) {
            $redirectUrl .= '?'.$queryString;
        }

        $event->setResponse(new RedirectResponse($redirectUrl, 302));
    }
}
