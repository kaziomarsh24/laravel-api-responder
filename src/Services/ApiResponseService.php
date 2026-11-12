<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Services;

use ArrayAccess;
use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Traversable;

class ApiResponseService
{
    /**
     * Additional custom metadata for the response.
     */
    protected array $meta = [];

    /**
     * Additional HTTP headers for the response.
     */
    protected array $headers = [];

    /**
     * Custom cookies for the response.
     */
    protected array $cookies = [];

    /**
     * Custom JSON encoding options.
     */
    protected ?int $jsonOptions = null;

    /**
     * Unique Trace ID for the current request.
     */
    protected ?string $traceId = null;

    /**
     * Create a new instance (supports method chaining).
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Set a custom trace ID.
     */
    public function withTraceId(string $traceId): static
    {
        $this->traceId = $traceId;
        return $this;
    }

    /**
     * Attach custom metadata to the response envelope.
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Add a single HTTP header.
     */
    public function withHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Add multiple HTTP headers.
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Attach a cookie to the response.
     */
    public function withCookie(mixed $cookie): static
    {
        $this->cookies[] = $cookie;
        return $this;
    }

    /**
     * Override JSON encoding options.
     */
    public function setJsonOptions(int $options): static
    {
        $this->jsonOptions = $options;
        return $this;
    }

    /**
     * Build a standardized success JSON response.
     *
     * Automatically handles:
     * - ($message, $data) or ($data)
     * - ResourceCollection, JsonResource, LengthAwarePaginator, CursorPaginator
     * - Arrayable, Traversable, raw arrays, and objects
     */
    public function success(
        mixed $message = null,
        mixed $data = null,
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        // Ergonomic parameter swapping: if first parameter is data and second is null
        if (!is_string($message) && $message !== null && $data === null) {
            $data = $message;
            $message = $this->getDefaultMessage('success', 'Success');
        } elseif ($message === null) {
            $message = $this->getDefaultMessage('success', 'Success');
        }

        [$resolvedData, $pagination] = $this->parseDataAndPagination($data);

        $statusKey     = $this->getKey('status', 'ok');
        $messageKey    = $this->getKey('message', 'message');
        $dataKey       = $this->getKey('data', 'data');
        $paginationKey = $this->getKey('pagination', 'pagination');
        $metaKey       = $this->getKey('meta', 'meta');

        $payload = [
            $statusKey  => true,
            $messageKey => (string) $message,
        ];

        // Include data key based on configuration or presence
        if ($resolvedData !== null || config('api-response.include_null_data', false)) {
            $payload[$dataKey] = $resolvedData;
        }

        // Include pagination if available or explicitly configured
        if ($pagination !== null) {
            $payload[$paginationKey] = $pagination;
        } elseif (config('api-response.pagination.include_when_null', false)) {
            $payload[$paginationKey] = null;
        }

        // Include custom meta if attached
        if (!empty($this->meta)) {
            $payload[$metaKey] = $this->meta;
        }

        return $this->createJsonResponse($payload, $statusCode, $headers);
    }

    /**
     * Build a standardized error JSON response.
     *
     * Gracefully accepts:
     * - string, array, Validator, ValidationException, MessageBag, Throwable, or null
     */
    public function error(
        mixed $message = null,
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $headers = []
    ): JsonResponse {
        if ($message === null) {
            $message = $this->getDefaultMessage('bad_request', 'Bad request');
        }

        $normalizedErrors = $this->normalizeErrors($errors);

        $statusKey  = $this->getKey('status', 'ok');
        $messageKey = $this->getKey('message', 'message');
        $errorsKey  = $this->getKey('errors', 'errors');
        $metaKey    = $this->getKey('meta', 'meta');

        $payload = [
            $statusKey  => false,
            $messageKey => (string) $message,
        ];

        if ($normalizedErrors !== null || config('api-response.include_null_errors', true)) {
            $payload[$errorsKey] = $normalizedErrors;
        }

        if (!empty($this->meta)) {
            $payload[$metaKey] = $this->meta;
        }

        return $this->createJsonResponse($payload, $statusCode, $headers);
    }

    /*
    |--------------------------------------------------------------------------
    | Semantic Convenience Shortcuts
    |--------------------------------------------------------------------------
    */

    /**
     * 201 Created Response.
     */
    public function created(
        mixed $data = null,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('created', 'Resource created successfully');
        return $this->success($message, $data, Response::HTTP_CREATED, $headers);
    }

    /**
     * 202 Accepted Response.
     */
    public function accepted(
        mixed $data = null,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('accepted', 'Request accepted');
        return $this->success($message, $data, Response::HTTP_ACCEPTED, $headers);
    }

    /**
     * 204 No Content Response.
     */
    public function noContent(array $headers = []): JsonResponse
    {
        return $this->createJsonResponse(null, Response::HTTP_NO_CONTENT, $headers);
    }

    /**
     * 400 Bad Request Response.
     */
    public function badRequest(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('bad_request', 'Bad request');
        return $this->error($message, $errors, Response::HTTP_BAD_REQUEST, $headers);
    }

    /**
     * 401 Unauthorized Response.
     */
    public function unauthorized(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('unauthorized', 'Unauthorized');
        return $this->error($message, $errors, Response::HTTP_UNAUTHORIZED, $headers);
    }

    /**
     * 403 Forbidden Response.
     */
    public function forbidden(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('forbidden', 'Forbidden');
        return $this->error($message, $errors, Response::HTTP_FORBIDDEN, $headers);
    }

    /**
     * 404 Not Found Response.
     */
    public function notFound(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('not_found', 'Resource not found');
        return $this->error($message, $errors, Response::HTTP_NOT_FOUND, $headers);
    }

    /**
     * 422 Unprocessable Entity / Validation Error Response.
     */
    public function validationError(
        mixed $errors,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('validation_error', 'Validation error');
        return $this->error($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY, $headers);
    }

    /**
     * 500 Internal Server Error Response.
     */
    public function serverError(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('server_error', 'Internal server error');
        return $this->error($message, $errors, Response::HTTP_INTERNAL_SERVER_ERROR, $headers);
    }

    /*
    |--------------------------------------------------------------------------
    | Data & Pagination Parsing Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve data payload and extract pagination metadata if present.
     *
     * @return array{0: mixed, 1: ?array}
     */
    public function parseDataAndPagination(mixed $data): array
    {
        if ($data === null) {
            return [null, null];
        }

        // Case 1: ResourceCollection (e.g. UserCollection or UserResource::collection($paginated))
        if ($data instanceof ResourceCollection) {
            $underlying = $data->resource;
            $pagination = $this->extractPaginationFromObject($underlying);

            try {
                $resolved = $data->resolve(request());
            } catch (Throwable) {
                $response = $data->response()->getData(true);
                $resolved = $response['data'] ?? $data->toArray(request());
            }

            return [$this->normalizeCollectionItems($resolved), $pagination];
        }

        // Case 2: Single JsonResource
        if ($data instanceof JsonResource) {
            try {
                $resolved = $data->resolve(request());
            } catch (Throwable) {
                $resolved = $data->toArray(request());
            }
            return [$resolved, null];
        }

        // Case 3: Paginators directly (LengthAwarePaginator, CursorPaginator, Paginator)
        if ($data instanceof LengthAwarePaginatorContract ||
            $data instanceof CursorPaginatorContract ||
            $data instanceof PaginatorContract ||
            $data instanceof AbstractPaginator ||
            $data instanceof AbstractCursorPaginator) {
            $pagination = $this->extractPaginationFromObject($data);
            $items = $this->normalizeCollectionItems($data->items());
            return [$items, $pagination];
        }

        // Case 4: Arrayable (e.g. Eloquent Collection, Model, etc.)
        if ($data instanceof Arrayable) {
            return [$data->toArray(), null];
        }

        // Case 5: JsonSerializable
        if ($data instanceof JsonSerializable) {
            return [$data->jsonSerialize(), null];
        }

        // Case 6: Traversable
        if ($data instanceof Traversable) {
            return [iterator_to_array($data), null];
        }

        return [$data, null];
    }

    /**
     * Extract pagination metadata from supported paginator instances.
     */
    protected function extractPaginationFromObject(mixed $paginator): ?array
    {
        if ($paginator instanceof LengthAwarePaginatorContract ||
            ($paginator instanceof AbstractPaginator && method_exists($paginator, 'total'))) {
            return [
                $this->getPaginationKey('total', 'total')               => (int) $paginator->total(),
                $this->getPaginationKey('count', 'count')               => (int) $paginator->count(),
                $this->getPaginationKey('per_page', 'per_page')         => (int) $paginator->perPage(),
                $this->getPaginationKey('current_page', 'current_page') => (int) $paginator->currentPage(),
                $this->getPaginationKey('total_pages', 'total_pages')   => (int) $paginator->lastPage(),
                $this->getPaginationKey('has_more', 'has_more')         => (bool) $paginator->hasMorePages(),
            ];
        }

        if ($paginator instanceof CursorPaginatorContract ||
            $paginator instanceof AbstractCursorPaginator) {
            return [
                $this->getPaginationKey('count', 'count')             => (int) $paginator->count(),
                $this->getPaginationKey('per_page', 'per_page')       => (int) $paginator->perPage(),
                $this->getPaginationKey('has_more', 'has_more')       => (bool) $paginator->hasMorePages(),
                $this->getPaginationKey('next_cursor', 'next_cursor') => $paginator->nextCursor()?->encode(),
                $this->getPaginationKey('prev_cursor', 'prev_cursor') => $paginator->previousCursor()?->encode(),
            ];
        }

        if ($paginator instanceof PaginatorContract ||
            $paginator instanceof AbstractPaginator) {
            return [
                $this->getPaginationKey('count', 'count')               => (int) $paginator->count(),
                $this->getPaginationKey('per_page', 'per_page')         => (int) $paginator->perPage(),
                $this->getPaginationKey('current_page', 'current_page') => (int) $paginator->currentPage(),
                $this->getPaginationKey('has_more', 'has_more')         => (bool) $paginator->hasMorePages(),
            ];
        }

        return null;
    }

    /**
     * Recursively normalize collection items (e.g. nested JsonResource or Arrayable).
     */
    protected function normalizeCollectionItems(mixed $items): mixed
    {
        if (!is_iterable($items)) {
            return $items;
        }

        $results = [];
        foreach ($items as $key => $item) {
            if ($item instanceof JsonResource) {
                try {
                    $results[$key] = $item->resolve(request());
                } catch (Throwable) {
                    $results[$key] = $item->toArray(request());
                }
            } elseif ($item instanceof Arrayable) {
                $results[$key] = $item->toArray();
            } elseif ($item instanceof JsonSerializable) {
                $results[$key] = $item->jsonSerialize();
            } else {
                $results[$key] = $item;
            }
        }

        return array_values($results);
    }

    /*
    |--------------------------------------------------------------------------
    | Error Normalization
    |--------------------------------------------------------------------------
    */

    /**
     * Gracefully normalize any error input into a consistent array format.
     */
    public function normalizeErrors(mixed $errors): mixed
    {
        if ($errors === null) {
            return null;
        }

        // Validator instance
        if ($errors instanceof ValidatorContract) {
            return $errors->errors()->toArray();
        }

        // ValidationException
        if ($errors instanceof ValidationException) {
            return $errors->errors();
        }

        // MessageBag
        if ($errors instanceof MessageBag) {
            return $errors->toArray();
        }

        // MessageProvider
        if ($errors instanceof MessageProvider) {
            return $errors->getMessageBag()->toArray();
        }

        // Throwable / Exception
        if ($errors instanceof Throwable) {
            if (config('api-response.debug_exceptions', config('app.debug', false))) {
                return [
                    'exception' => get_class($errors),
                    'message'   => $errors->getMessage(),
                    'file'      => $errors->getFile(),
                    'line'      => $errors->getLine(),
                    'trace'     => collect($errors->getTrace())->take(10)->toArray(),
                ];
            }

            return ['error' => [$errors->getMessage()]];
        }

        // Plain string error
        if (is_string($errors)) {
            return ['error' => [$errors]];
        }

        // Arrayable
        if ($errors instanceof Arrayable) {
            return $errors->toArray();
        }

        if (is_array($errors)) {
            return $errors;
        }

        return (array) $errors;
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Response Creation & Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create the final JsonResponse with headers, cookies, trace ID, and options applied.
     */
    protected function createJsonResponse(mixed $data, int $statusCode, array $headers = []): JsonResponse
    {
        $defaultHeaders = (array) config('api-response.headers', []);
        $mergedHeaders  = array_merge($defaultHeaders, $this->headers, $headers);

        // Process Trace ID
        if (config('api-response.trace_id.enabled', true)) {
            $traceId = $this->getOrGenerateTraceId();
            $traceHeader = (string) config('api-response.trace_id.header_name', 'X-Request-Id');
            $mergedHeaders[$traceHeader] = $traceId;

            $onlyOnError = (bool) config('api-response.trace_id.only_on_error', false);
            $traceKey    = (string) config('api-response.keys.trace_id', 'trace_id');

            if (is_array($data)) {
                $statusKey = $this->getKey('status', 'ok');
                $isSuccess = ($data[$statusKey] ?? true) === true;

                if (! $onlyOnError || ! $isSuccess) {
                    $data[$traceKey] = $traceId;
                }
            }
        }

        $jsonOptions = $this->jsonOptions ?? (int) config(
            'api-response.json_options',
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $response = new JsonResponse($data, $statusCode, $mergedHeaders, $jsonOptions);

        foreach ($this->cookies as $cookie) {
            $response->withCookie($cookie);
        }

        // Reset state for potential singleton or reused instances
        $this->meta        = [];
        $this->headers     = [];
        $this->cookies     = [];
        $this->traceId     = null;
        $this->jsonOptions = null;

        return $response;
    }

    /**
     * Resolve or generate a unique Trace ID.
     */
    public function getOrGenerateTraceId(): string
    {
        if ($this->traceId !== null) {
            return $this->traceId;
        }

        $headerName = (string) config('api-response.trace_id.header_name', 'X-Request-Id');
        $request = request();

        if ($request && $request->hasHeader($headerName)) {
            return (string) $request->header($headerName);
        }

        return 'req_' . bin2hex(random_bytes(8));
    }

    /**
     * Register global API exception handling for Laravel 10/11/12.
     */
    public function registerExceptionHandler(mixed $exceptions): void
    {
        \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::register($exceptions);
    }

    /**
     * Render an exception into a standardized JsonResponse.
     */
    public function renderException(Throwable $e, ?\Illuminate\Http\Request $request = null): ?JsonResponse
    {
        return \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::render($e, $request);
    }

    /**
     * Get envelope key name from config with fallback.
     */
    protected function getKey(string $name, string $default): string
    {
        return (string) config("api-response.keys.{$name}", $default);
    }

    /**
     * Get pagination key name from config with fallback.
     */
    protected function getPaginationKey(string $name, string $default): string
    {
        return (string) config("api-response.pagination.{$name}", $default);
    }

    /**
     * Get default message from config with fallback.
     */
    protected function getDefaultMessage(string $key, string $default): string
    {
        return (string) config("api-response.messages.{$key}", $default);
    }
}
