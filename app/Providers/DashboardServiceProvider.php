<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Dashboard\Application\Contracts\DashboardMapperContract;
use Core\Dashboard\Application\Mappers\DashboardMapper;
use Core\Dashboard\Application\Mappers\DashboardResponseMapper;
use Core\Dashboard\Application\Services\DashboardQueryService;
use Core\Dashboard\Domain\Contracts\DashboardBoardContract;
use Core\Dashboard\Domain\Contracts\DashboardFeedContract;
use Core\Dashboard\Domain\Contracts\DashboardMetricsContract;
use Core\Dashboard\Infrastructure\Repositories\DashboardBoardRepository;
use Core\Dashboard\Infrastructure\Repositories\DashboardFeedRepository;
use Core\Dashboard\Infrastructure\Repositories\DashboardMetricsRepository;
use Illuminate\Support\ServiceProvider;

final class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardMetricsContract::class, DashboardMetricsRepository::class);
        $this->app->bind(DashboardBoardContract::class, DashboardBoardRepository::class);
        $this->app->bind(DashboardFeedContract::class, DashboardFeedRepository::class);
        $this->app->bind(DashboardMapperContract::class, DashboardMapper::class);
        $this->app->bind(DashboardResponseMapper::class, DashboardResponseMapper::class);
        $this->app->bind(DashboardQueryService::class, DashboardQueryService::class);
    }
}
