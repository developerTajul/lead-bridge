<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Contracts;

/**
 * PORT: transaction boundary around a use case.
 */
interface TransactionManagerContract
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    /**
     * Executes the callback inside a transaction and returns its result.
     * Rolls back and rethrows when the callback throws.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function run(callable $callback): mixed;
}
