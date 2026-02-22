<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class RateLimiterService
{
    private ?int $retryAfter = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactoryInterface $registerFormLimiter,
        private readonly RateLimiterFactoryInterface $contactFormLimiter,
        private readonly RateLimiterFactoryInterface $newsletterFormLimiter,
    ) {
    }

    public function checkLimit(RateLimiterFactoryInterface $limiter, ?string $key = null): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->isMethod('POST')) {
            return true;
        }

        $key ??= $request->getClientIp();
        $limit = $limiter->create($key)->consume();

        if (!$limit->isAccepted()) {
            $this->retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            return false;
        }

        return true;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter ?? null;
    }

    public function checkRegister(?string $key = null): bool
    {
        return $this->checkLimit($this->registerFormLimiter, $key);
    }

    public function checkContact(?string $key = null): bool
    {
        return $this->checkLimit($this->contactFormLimiter, $key);
    }

    public function checkNewsletter(?string $key = null): bool
    {
        return $this->checkLimit($this->newsletterFormLimiter, $key);
    }
}
