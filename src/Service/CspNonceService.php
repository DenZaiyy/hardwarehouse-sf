<?php

namespace App\Service;

class CspNonceService
{
    private string $nonce = '';

    public function generate(): string
    {
        $this->nonce = base64_encode(random_bytes(16));
        return $this->nonce;
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }
}
