<?php

namespace App\Exception\Api;

class ApiUnavailableException extends ApiException
{
    public function __construct(string $endpoint)
    {
        parent::__construct("API unavailable at: $endpoint", 503);
    }
}
