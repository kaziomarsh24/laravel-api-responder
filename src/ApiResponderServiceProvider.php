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

        if ($this->app->bound('router')) {
            $this->app['router']->aliasMiddleware(
                'api.force_json',
                \KaziOmar\ApiResponder\Middleware\ForceJsonResponse::class
            );
        }

        // Auto-register exception handling if enabled
        if (config('api-response.exceptions.auto_handle', true)) {
            if ($this->app->bound(\Illuminate\Contracts\Debug\ExceptionHandler::class)) {
                $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
                if (method_exists($handler, 'renderable')) {
                    $handler->renderable(function (\Throwable $e, $request) {
                        return \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::render($e, $request);
                    });
                }
            }
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
