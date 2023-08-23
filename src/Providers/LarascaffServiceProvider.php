<?php

namespace Phyple\Larascaff\Providers;

use Illuminate\Support\ServiceProvider;
use Phyple\Larascaff\Consoles\Generator\CreateRepositoryCommand;
use Phyple\Larascaff\Consoles\Generator\CreateRequestCommand;
use Phyple\Larascaff\Consoles\Generator\CreateServiceCommand;

class LarascaffServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([
            CreateServiceCommand::class,
            CreateRepositoryCommand::class,
        ]);
    }
}
