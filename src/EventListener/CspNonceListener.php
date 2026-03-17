<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(event: 'kernel.request', priority: 10)]
#[AsEventListener(event: 'kernel.response')]
class CspNonceListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $nonce = base64_encode(random_bytes(16));
        // On le stocke en request attribute pour Twig
        $event->getRequest()->attributes->set('csp_nonce', $nonce);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $nonce = $event->getRequest()->attributes->get('csp_nonce');
        if (!is_string($nonce) || '' === $nonce) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; ".
            "script-src 'self' 'nonce-$nonce'; ".
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ".
            "font-src 'self' https://fonts.gstatic.com data:; ".
            "img-src 'self' data: https://api.hardwarehouse.fr https://picsum.photos https://fastly.picsum.photos; ".
            "connect-src 'self' https://api.hardwarehouse.fr; ".
            "form-action 'self'; ".
            "frame-ancestors 'none'; ".
            "base-uri 'self';"
        );
    }
}
