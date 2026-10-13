<?php

use App\Providers\ActivityServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\LeadServiceProvider;

return [
    AppServiceProvider::class,
    LeadServiceProvider::class,
    ActivityServiceProvider::class,
];
