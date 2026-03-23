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
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ".
            "font-src 'self' https://fonts.gstatic.com data:; ".
            "img-src 'self' data: https://api.hardwarehouse.fr https://picsum.photos https://fastly.picsum.photos; ".
            "connect-src 'self' https://api.hardwarehouse.fr; ".
            'frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/; '.
            "form-action 'self'; ".
            "frame-ancestors 'none'; ".
            "base-uri 'self';"
        );
    }
}
