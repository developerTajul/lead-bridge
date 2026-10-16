<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use Core\Activity\Domain\Events\ActivityCreatedEvent;
use Core\Auth\Application\Contracts\AuthMapperContract;
use Core\Auth\Application\Mappers\AuthRequestMapper;
use Core\Auth\Application\Services\AuthService;
use Core\Auth\Domain\Contracts\AuthRepositoryContract;
use Core\Auth\Infrastructure\Repositories\AuthRepository;
use Core\Lead\Application\Listeners\UpdateLeadStatusOnActivityCreated;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\FileUploaderContract;
use Core\Shared\Domain\Contracts\PasswordHasherContract;
use Core\Shared\Domain\Contracts\SlugCheckerContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\Shared\Infrastructure\Database\LaravelTransactionManager;
use Core\Shared\Infrastructure\Events\LaravelEventDispatcher;
use Core\Shared\Infrastructure\Repositories\EloquentSlugChecker;
use Core\Shared\Infrastructure\Security\LaravelPasswordHasher;
use Core\Shared\Infrastructure\Storage\LaravelFileUploader;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Morph aliases stored in a polymorphic column, mapped to their models.
     * The Activity module writes these aliases; Eloquent resolves them back
     * to a class when a trackable relation is loaded.
     *
     * @var array<string, class-string>
     */
    private const MORPH_MAP = [
        'lead' => Lead::class,
        'deal' => Deal::class,
        'contact' => Contact::class,
        'company' => Company::class,
    ];

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

        $this->app->bind(EventDispatcherContract::class,LaravelEventDispatcher::class);



    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap(self::MORPH_MAP);

        Event::listen(
            ActivityCreatedEvent::class,
            UpdateLeadStatusOnActivityCreated::class
        );
    }
}