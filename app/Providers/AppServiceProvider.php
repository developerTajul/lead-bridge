<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Auth\Application\Contracts\AuthMapperContract;
use Core\Auth\Application\Mappers\AuthRequestMapper;
use Core\Auth\Application\Services\AuthService;
use Core\Auth\Domain\Contracts\AuthRepositoryContract;
use Core\Auth\Infrastructure\Repositories\AuthRepository;
use Core\Shared\Domain\Contracts\FileUploaderContract;
use Core\Shared\Domain\Contracts\PasswordHasherContract;
use Core\Shared\Domain\Contracts\SlugCheckerContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Infrastructure\Database\LaravelTransactionManager;
use Core\Shared\Infrastructure\Repositories\EloquentSlugChecker;
use Core\Shared\Infrastructure\Security\LaravelPasswordHasher;
use Core\Shared\Infrastructure\Storage\LaravelFileUploader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthRepositoryContract::class, AuthRepository::class);

        $this->app->bind(AuthMapperContract::class, AuthRequestMapper::class);
        $this->app->bind(AuthService::class, AuthService::class);

        $this->app->bind(TransactionManagerContract::class, LaravelTransactionManager::class);

        $this->app->bind(FileUploaderContract::class, LaravelFileUploader::class);

        $this->app->bind(SlugCheckerContract::class, EloquentSlugChecker::class);

        $this->app->bind(PasswordHasherContract::class, LaravelPasswordHasher::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}