<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder;

use Illuminate\Support\ServiceProvider;
use KaziOmar\ApiResponder\Services\ApiResponseService;

class ApiResponderServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge default configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/api-response.php',
            'api-response'
        );

        // Bind main service as a singleton
        $this->app->singleton('api-responder', function () {
            return new ApiResponseService();
        });

        $this->app->alias('api-responder', ApiResponseService::class);

        // Ensure helper functions are immediately available
        require_once __DIR__ . '/Helpers/api_response.php';
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/api-response.php' => config_path('api-response.php'),
            ], 'api-responder-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['api-responder', ApiResponseService::class];
    }
}
