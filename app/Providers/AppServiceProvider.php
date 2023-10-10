<?php

namespace App\Providers;

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DataSenderService;

class AppServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->bind('DataSender', function ($app) {
            return new DataSenderService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
