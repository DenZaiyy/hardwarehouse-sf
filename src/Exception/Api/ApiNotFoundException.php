<?php

namespace App\Exception\Api;

class ApiNotFoundException extends ApiException
{
    public function __construct(string $endpoint)
    {
        parent::__construct("Resource not found at: $endpoint", 404);
    }
}
