<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\CspNonceService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CspNonceExtension extends AbstractExtension
{
    public function __construct(private readonly CspNonceService $nonceService)
    {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->getNonce(...)),
        ];
    }

    public function getNonce(): string
    {
        return $this->nonceService->getNonce();
    }
}
