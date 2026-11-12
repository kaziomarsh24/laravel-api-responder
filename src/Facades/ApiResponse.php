<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Facades;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Facade;
use KaziOmar\ApiResponder\Services\ApiResponseService;

/**
 * @method static ApiResponseService make()
 * @method static ApiResponseService withMeta(array $meta)
 * @method static ApiResponseService withHeader(string $key, string $value)
 * @method static ApiResponseService withHeaders(array $headers)
 * @method static ApiResponseService withCookie(mixed $cookie)
 * @method static ApiResponseService setJsonOptions(int $options)
 * @method static JsonResponse success(mixed $message = null, mixed $data = null, int $statusCode = 200, array $headers = [])
 * @method static JsonResponse error(mixed $message = null, mixed $errors = null, int $statusCode = 400, array $headers = [])
 * @method static JsonResponse created(mixed $data = null, ?string $message = null, array $headers = [])
 * @method static JsonResponse accepted(mixed $data = null, ?string $message = null, array $headers = [])
 * @method static JsonResponse noContent(array $headers = [])
 * @method static JsonResponse badRequest(?string $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse unauthorized(?string $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse forbidden(?string $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse notFound(?string $message = null, mixed $errors = null, array $headers = [])
 * @method static JsonResponse validationError(mixed $errors, ?string $message = null, array $headers = [])
 * @method static JsonResponse serverError(?string $message = null, mixed $errors = null, array $headers = [])
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
