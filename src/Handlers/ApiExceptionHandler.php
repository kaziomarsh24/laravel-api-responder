<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Handlers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use KaziOmar\ApiResponder\Facades\ApiResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionHandler
{
    /**
     * Register exception handling on Laravel 10 (Handler) or Laravel 11/12 (Exceptions).
     */
    public static function register(mixed $exceptions): void
    {
        // Laravel 11/12 bootstrap/app.php ($exceptions is Illuminate\Foundation\Configuration\Exceptions)
        if (method_exists($exceptions, 'render')) {
            $exceptions->render(function (Throwable $e, ?Request $request = null) {
                return static::render($e, $request);
            });
            return;
        }

        // Laravel 10 app/Exceptions/Handler.php ($exceptions is Illuminate\Foundation\Exceptions\Handler)
        if (method_exists($exceptions, 'renderable')) {
            $exceptions->renderable(function (Throwable $e, ?Request $request = null) {
                return static::render($e, $request);
            });
        }
    }

    /**
     * Render an exception into a standardized ApiResponse JsonResponse.
     * Returns null if non-API request and only_api_requests is enabled.
     */
    public static function render(Throwable $e, ?Request $request = null): ?JsonResponse
    {
        $request ??= request();

        // If configured to only touch API requests, skip standard browser web requests
        if ($request && config('api-response.exceptions.only_api_requests', true)) {
            $isApi = $request->expectsJson() || $request->is('api/*') || (function_exists('app') && app()->runningUnitTests());
            if (! $isApi) {
                return null;
            }
        }

        // Unpack wrapped exceptions from Laravel's prepareException
        if ($e instanceof NotFoundHttpException && $e->getPrevious() instanceof ModelNotFoundException) {
            $e = $e->getPrevious();
        }

        // Automatically report exception to laravel.log / Sentry / Bugsnag if configured
        if (config('api-response.exceptions.log_unhandled', true) && function_exists('report')) {
            try {
                report($e);
            } catch (Throwable) {
                // Silently ignore reporting errors to avoid loop
            }
        }

        // 1. ValidationException (FormRequest or Validator)
        if ($e instanceof ValidationException) {
            $msg = $e->getMessage() ?: (string) config('api-response.messages.validation_error', 'Validation failed');
            return ApiResponse::validationError($e->errors(), $msg);
        }

        // 2. ModelNotFoundException (e.g. Model::findOrFail)
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            $ids = implode(', ', array_map(fn($id) => is_array($id) ? json_encode($id) : (string) $id, (array) $e->getIds()));
            $detail = $ids !== '' ? "{$model} with ID [{$ids}] not found." : "{$model} not found.";
            $msg = (string) config('api-response.messages.not_found', 'Resource not found');

            return ApiResponse::notFound($msg, ['error' => [$detail]]);
        }

        // 3. NotFoundHttpException (Route 404)
        if ($e instanceof NotFoundHttpException) {
            $msg = (string) config('api-response.messages.not_found', 'Resource not found');
            $detail = $e->getMessage() ?: 'The requested endpoint or URL was not found on this server.';

            return ApiResponse::notFound($msg, ['error' => [$detail]]);
        }

        // 4. AuthenticationException (Auth 401)
        if ($e instanceof AuthenticationException) {
            $msg = (string) config('api-response.messages.unauthorized', 'Unauthorized access');
            $detail = $e->getMessage() ?: 'Unauthenticated. Please provide a valid Bearer token.';

            return ApiResponse::unauthorized($msg, ['error' => [$detail]]);
        }

        // 5. AuthorizationException or AccessDeniedHttpException (Policy 403)
        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            $msg = (string) config('api-response.messages.forbidden', 'Access forbidden');
            $detail = $e->getMessage() ?: 'You do not have permission to access or perform this action.';

            return ApiResponse::forbidden($msg, ['error' => [$detail]]);
        }

        // 6. TooManyRequestsHttpException (Rate limiting 429)
        if ($e instanceof TooManyRequestsHttpException) {
            $msg = (string) config('api-response.messages.too_many_requests', 'Too many requests. Please slow down.');
            $headers = $e->getHeaders();

            return ApiResponse::error(
                $msg,
                ['error' => ['Rate limit exceeded. Please wait before retrying.']],
                Response::HTTP_TOO_MANY_REQUESTS,
                $headers
            );
        }

        // 7. MethodNotAllowedHttpException (405)
        if ($e instanceof MethodNotAllowedHttpException) {
            $msg = (string) config('api-response.messages.method_not_allowed', 'Method not allowed');
            return ApiResponse::error(
                $msg,
                ['error' => [$e->getMessage()]],
                Response::HTTP_METHOD_NOT_ALLOWED,
                $e->getHeaders()
            );
        }

        // 8. Other HTTP Exceptions
        if ($e instanceof HttpExceptionInterface) {
            return ApiResponse::error(
                $e->getMessage() ?: 'HTTP Error',
                ['error' => [$e->getMessage()]],
                $e->getStatusCode(),
                $e->getHeaders()
            );
        }

        // 9. Generic 500 Server Error
        $msg = (string) config('api-response.messages.server_error', 'An internal server error occurred');
        return ApiResponse::serverError($msg, $e);
    }
}
