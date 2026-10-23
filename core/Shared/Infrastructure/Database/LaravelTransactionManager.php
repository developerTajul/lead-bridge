<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Database;

use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Illuminate\Support\Facades\DB;

/**
 * ADAPTER: Laravel implementation of the transaction boundary port.
 * Canonical home for shared DB-level structural adapters.
 */
final class LaravelTransactionManager implements TransactionManagerContract
{
    public function beginTransaction(): void
    {
        DB::beginTransaction();
    }

    public function commit(): void
    {
        DB::commit();
    }

    public function rollback(): void
    {
        DB::rollBack();
    }

    /**
     * Runs the callback in a transaction, letting Laravel pick the level: a
     * savepoint when a transaction is already open, a real one otherwise.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
