<?php

declare(strict_types=1);

namespace Core\User\Application\DTOs\Requests;

/**
 * Carries validated login credentials into the service. Immutable.
 */
readonly class UserLoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}