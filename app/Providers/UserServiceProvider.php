<?php

declare(strict_types=1);

namespace App\Providers;

use Core\User\Application\Contracts\UserMapperContract;
use Core\User\Application\Mappers\UserListFilterMapper;
use Core\User\Application\Mappers\UserListResponseMapper;
use Core\User\Application\Mappers\UserMapper;
use Core\User\Application\Mappers\UserResponseMapper;
use Core\User\Application\Services\UserQueryService;
use Core\User\Application\Services\UserService;
use Core\User\Domain\Contracts\UserQueryContract;
use Core\User\Domain\Contracts\UserRepositoryContract;
use Core\User\Infrastructure\Repositories\UserQueryRepository;
use Core\User\Infrastructure\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the user module: the write side through UserRepositoryContract, the
 * read side through UserQueryContract.
 */
final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryContract::class, UserRepository::class);
        $this->app->bind(UserQueryContract::class, UserQueryRepository::class);
        $this->app->bind(UserMapperContract::class, UserMapper::class);
        $this->app->bind(UserListFilterMapper::class, UserListFilterMapper::class);
        $this->app->bind(UserListResponseMapper::class, UserListResponseMapper::class);
        $this->app->bind(UserResponseMapper::class, UserResponseMapper::class);
        $this->app->bind(UserService::class, UserService::class);
        $this->app->bind(UserQueryService::class, UserQueryService::class);
    }
}
