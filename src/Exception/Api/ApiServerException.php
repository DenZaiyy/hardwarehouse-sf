<?php

namespace App\Exception\Api;

class ApiServerException extends ApiException
{
    public function __construct(string $endpoint)
    {
        parent::__construct("API server error at: $endpoint", 500);
    }
}
