<?php

declare(strict_types=1);

declare(strict_types=1);

namespace App\Providers;

use Core\Contact\Application\Contracts\ContactMapperContract;
use Core\Contact\Application\Mappers\ContactMapper;
use Core\Contact\Application\Mappers\ContactResponseMapper;
use Core\Contact\Application\Services\ContactQueryService;
use Core\Contact\Application\Services\ContactService;
use Core\Contact\Domain\Contracts\ContactQueryContract;
use Core\Contact\Domain\Contracts\ContactRepositoryContract;
use Core\Contact\Infrastructure\Repositories\ContactEloquentRepository;
use Illuminate\Support\ServiceProvider;

final class ContactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContactRepositoryContract::class, ContactEloquentRepository::class);
        $this->app->bind(ContactQueryContract::class, ContactEloquentRepository::class);
        $this->app->bind(ContactMapperContract::class, ContactMapper::class);
        $this->app->bind(ContactResponseMapper::class, ContactResponseMapper::class);
        $this->app->bind(ContactService::class, ContactService::class);
        $this->app->bind(ContactQueryService::class, ContactQueryService::class);
    }
}
