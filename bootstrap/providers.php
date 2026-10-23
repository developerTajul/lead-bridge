<?php

use App\Providers\ActivityServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CompanyServiceProvider;
use App\Providers\ContactServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\DealServiceProvider;
use App\Providers\LeadServiceProvider;
use App\Providers\PipelineServiceProvider;
use App\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    LeadServiceProvider::class,
    ActivityServiceProvider::class,
    CompanyServiceProvider::class,
    ContactServiceProvider::class,
    PipelineServiceProvider::class,
    DealServiceProvider::class,
    DashboardServiceProvider::class,
    UserServiceProvider::class,
];
