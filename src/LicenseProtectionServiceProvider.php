<?php

namespace KaaliiSecurity;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

class KaaliiSecurityServiceProvider extends ServiceProvider
{
    public function boot(Kernel $kernel)
    {
        // dd("License Protect package");
        // // Add global middleware without editing Kernel
        $kernel->pushMiddleware(\KaaliiSecurity\Middleware\SecurityCheckMiddleware::class);
        // Register middleware globally
        // $this->app['router']->pushMiddlewareToGroup('web', LicenseCheck::class);
        // $this->app['router']->pushMiddlewareToGroup('api', LicenseCheck::class);
    }
}
