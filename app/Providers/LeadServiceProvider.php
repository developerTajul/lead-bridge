<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Lead\Application\Contracts\LeadMapperContract;
use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Shared\Application\Contracts\QueueManagerContract;
use Core\Lead\Application\Mappers\LeadMapper;
use Core\Lead\Application\Services\LeadService;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Shared\Infrastructure\Queues\LaravelQueueManager;
use Core\Lead\Infrastructure\Repositories\LeadRepository;
use Illuminate\Support\ServiceProvider;

class LeadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LeadServiceInterface::class, LeadService::class);
        $this->app->bind(LeadMapperContract::class, LeadMapper::class);
        $this->app->bind(LeadRepositoryContract::class, LeadRepository::class);
        $this->app->bind(QueueManagerContract::class, LaravelQueueManager::class);
    }
}
