<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Company\Application\Contracts\CompanyMapperContract;
use Core\Company\Application\Contracts\CompanyQueryContract;
use Core\Company\Application\Mappers\CompanyMapper;
use Core\Company\Application\Mappers\CompanyResponseMapper;
use Core\Company\Application\Services\CompanyQueryService;
use Core\Company\Application\Services\CompanyService;
use Core\Company\Domain\Contracts\CompanyRepositoryContract;
use Core\Company\Infrastructure\Repositories\CompanyEloquentRepository;
use Illuminate\Support\ServiceProvider;

class CompanyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanyRepositoryContract::class, CompanyEloquentRepository::class);
        $this->app->bind(CompanyQueryContract::class, CompanyEloquentRepository::class);
        $this->app->bind(CompanyMapperContract::class, CompanyMapper::class);
        $this->app->bind(CompanyResponseMapper::class, CompanyResponseMapper::class);
        $this->app->bind(CompanyService::class, CompanyService::class);
        $this->app->bind(CompanyQueryService::class, CompanyQueryService::class);
    }
}
