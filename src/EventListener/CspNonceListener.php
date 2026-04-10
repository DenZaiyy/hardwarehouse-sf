<?php

namespace App\EventListener;

use App\Service\CspNonceService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(event: 'kernel.request', priority: 10)]
#[AsEventListener(event: 'kernel.response')]
class CspNonceListener
{
    public function __construct(private readonly CspNonceService $nonceService)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->nonceService->generate();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $nonce = $this->nonceService->getNonce();
        if ('' === $nonce) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; ".
            "manifest-src 'self'; ".
            "script-src 'self' 'nonce-$nonce' 'strict-dynamic'; ".
            "style-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://fonts.axept.io https://fonts.googleapis.com; ".
            "font-src 'self' https://fonts.gstatic.com https://fonts.axept.io data:; ".
            "img-src 'self' data: https://api.hardwarehouse.fr https://axeptio.imgix.net https://www.googletagmanager.com https://fonts.gstatic.com https://picsum.photos https://fastly.picsum.photos; ".
            "connect-src 'self' https://www.googletagmanager.com https://api.axept.io https://region1.google-analytics.com https://static.axept.io/ https://client.axept.io/ https://api.hardwarehouse.fr; ".
            'frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/; '.
            "form-action 'self'; ".
            "frame-ancestors 'none'; ".
            "base-uri 'self';"
        );
    }
}
