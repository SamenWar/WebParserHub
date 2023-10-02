<?php

namespace App\Providers;

use App\Services\PhotoDownloader;
use Illuminate\Support\ServiceProvider;

class PhotoDownloaderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PhotoDownloader::class, function ($app) {
            $proxyConfigPath = config_path('proxy.json');
            if (!file_exists($proxyConfigPath)) {
                throw new \Exception('Proxy configuration file not found.');
            }

            $proxyConfig = json_decode(file_get_contents($proxyConfigPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse proxy configuration file.');
            }

            return new PhotoDownloader($proxyConfig);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
