<?php

namespace App\Support\Workflow;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InvalidTransitionException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 422;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return [];
    }

    public static function between(string $from, string $to): self
    {
        return new self("Cannot transition from [{$from}] to [{$to}].");
    }
}
