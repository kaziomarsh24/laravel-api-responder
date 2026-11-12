<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Facades;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Facade;
use KaziOmar\ApiResponder\Services\ApiResponseService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method static ApiResponseService make()
 * @method static ApiResponseService withMeta(array $meta)
 * @method static ApiResponseService withHeader(string $key, string $value)
 * @method static ApiResponseService withHeaders(array $headers)
 * @method static ApiResponseService withCookie(mixed $cookie)
 * @method static ApiResponseService withTraceId(string $traceId)
 * @method static string getOrGenerateTraceId()
 * @method static void resetState()
 * @method static void registerExceptionHandler(mixed $exceptions)
 * @method static JsonResponse|null renderException(\Throwable $e, ?\Illuminate\Http\Request $request = null)
 * @method static ApiResponseService setJsonOptions(int $options)
 * @method static JsonResponse success(mixed $message = null, mixed $data = null, int $statusCode = 200, array $headers = [])
 * @method static JsonResponse error(mixed $message = null, mixed $errors = null, int $statusCode = 400, array $headers = [])
 * @method static JsonResponse created(mixed $data = null, mixed $message = null, array $headers = [])
 * @method static JsonResponse accepted(mixed $data = null, mixed $message = null, array $headers = [])
 * @method static Response noContent(array $headers = [])
 * @method static JsonResponse badRequest(mixed $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse unauthorized(mixed $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse forbidden(mixed $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse notFound(mixed $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse validationError(mixed $errors, mixed $message = null, array $headers = [])
 * @method static JsonResponse serverError(mixed $message = null, mixed $errors = null, array $headers = [])
 * @method static array parseDataAndPagination(mixed $data)
 * @method static mixed normalizeErrors(mixed $errors)
 *
 * @see ApiResponseService
 */
class ApiResponse extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'api-responder';
    }
}
