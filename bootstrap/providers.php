<?php

use App\Providers\ApiRateLimiter;

return [
    App\Providers\AppServiceProvider::class,
    ApiRateLimiter::class,
];
