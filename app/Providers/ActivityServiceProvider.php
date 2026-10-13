<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\Mappers\ActivityMapper;
use Core\Activity\Application\Mappers\ActivityResponseMapper;
use Core\Activity\Application\Services\ActivityService;
use Core\Activity\Domain\Contracts\ActivityRepositoryContract;
use Core\Activity\Infrastructure\Repositories\ActivityRepository;
use Illuminate\Support\ServiceProvider;

class ActivityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityRepositoryContract::class, ActivityRepository::class);
        $this->app->bind(ActivityMapperContract::class, ActivityMapper::class);
        $this->app->bind(ActivityResponseMapper::class, ActivityResponseMapper::class);
        $this->app->bind(ActivityService::class, ActivityService::class);
    }
}