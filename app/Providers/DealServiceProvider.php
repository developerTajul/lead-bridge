<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Deal\Application\Contracts\DealMapperContract;
use Core\Deal\Application\Mappers\DealMapper;
use Core\Deal\Application\Mappers\DealResponseMapper;
use Core\Deal\Application\Services\DealQueryService;
use Core\Deal\Application\Services\DealService;
use Core\Deal\Domain\Contracts\DealFormOptionsContract;
use Core\Deal\Domain\Contracts\DealQueryContract;
use Core\Deal\Domain\Contracts\DealRepositoryContract;
use Core\Deal\Infrastructure\Repositories\DealEloquentRepository;
use Core\Deal\Infrastructure\Repositories\DealFormOptionsRepository;
use Illuminate\Support\ServiceProvider;

final class DealServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DealRepositoryContract::class, DealEloquentRepository::class);
        $this->app->bind(DealQueryContract::class, DealEloquentRepository::class);
        $this->app->bind(DealFormOptionsContract::class, DealFormOptionsRepository::class);
        $this->app->bind(DealMapperContract::class, DealMapper::class);
        $this->app->bind(DealResponseMapper::class, DealResponseMapper::class);
        $this->app->bind(DealService::class, DealService::class);
        $this->app->bind(DealQueryService::class, DealQueryService::class);
    }
}
