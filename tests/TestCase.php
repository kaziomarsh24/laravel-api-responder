<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Tests;

use KaziOmar\ApiResponder\ApiResponderServiceProvider;
use KaziOmar\ApiResponder\Facades\ApiResponse;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ApiResponderServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ApiResponse' => ApiResponse::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('api-response', require __DIR__ . '/../config/api-response.php');
    }
}
