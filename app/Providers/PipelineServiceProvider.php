<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Pipeline\Application\Contracts\PipelineMapperContract;
use Core\Pipeline\Application\Mappers\KanbanBoardResponseMapper;
use Core\Pipeline\Application\Mappers\PipelineMapper;
use Core\Pipeline\Application\Mappers\PipelineResponseMapper;
use Core\Pipeline\Application\Services\PipelineQueryService;
use Core\Pipeline\Application\Services\PipelineService;
use Core\Pipeline\Domain\Contracts\PipelineQueryContract;
use Core\Pipeline\Domain\Contracts\PipelineRepositoryContract;
use Core\Pipeline\Infrastructure\Repositories\PipelineEloquentRepository;
use Illuminate\Support\ServiceProvider;

final class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PipelineRepositoryContract::class, PipelineEloquentRepository::class);
        $this->app->bind(PipelineQueryContract::class, PipelineEloquentRepository::class);
        $this->app->bind(PipelineMapperContract::class, PipelineMapper::class);
        $this->app->bind(PipelineResponseMapper::class, PipelineResponseMapper::class);
        $this->app->bind(KanbanBoardResponseMapper::class, KanbanBoardResponseMapper::class);
        $this->app->bind(PipelineService::class, PipelineService::class);
        $this->app->bind(PipelineQueryService::class, PipelineQueryService::class);
    }
}
