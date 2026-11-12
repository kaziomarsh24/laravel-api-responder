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
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use JsonSerializable;
use PDOException;
use Stringable;
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
     * Set a custom trace ID.
     */
    public function withTraceId(string $traceId): static
    {
        $this->traceId = $traceId;
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
     */
    public function success(
        mixed $message = null,
        mixed $data = null,
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        // Ergonomic parameter swapping: if first parameter is data and second is null
        if ($message !== null && $data === null) {
            if ($message instanceof Stringable) {
                $message = (string) $message;
            } elseif (is_array($message) || (is_object($message) && !($message instanceof Stringable))) {
                $data = $message;
                $message = $this->getDefaultMessage('success', 'Success');
            }
        } elseif ($message === null) {
            $message = $this->getDefaultMessage('success', 'Success');
        }

        [$resolvedData, $pagination] = $this->parseDataAndPagination($data);

        $statusKey     = $this->getKey('status', 'ok');
        $messageKey    = $this->getKey('message', 'message');
        $dataKey       = $this->getKey('data', 'data');
        $paginationKey = $this->getKey('pagination', 'pagination');
        $metaKey       = $this->getKey('meta', 'meta');

        $messageStr = ($message instanceof Stringable || is_scalar($message))
            ? (string) $message
            : $this->getDefaultMessage('success', 'Success');

        $payload = [
            $statusKey  => true,
            $messageKey => $messageStr,
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
     */
    public function error(
        mixed $message = null,
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $headers = []
    ): JsonResponse {
        // Ergonomic parameter swapping: if 1st argument is error payload and 2nd is null
        if ($message !== null && $errors === null) {
            if (is_array($message) ||
                $message instanceof ValidatorContract ||
                $message instanceof ValidationException ||
                $message instanceof MessageBag ||
                $message instanceof Throwable) {
                $errors = $message;
                $message = $this->getDefaultMessage('bad_request', 'Bad request');
            }
        } elseif ($message === null) {
            $message = $this->getDefaultMessage('bad_request', 'Bad request');
        }

        $normalizedErrors = $this->normalizeErrors($errors);

        $statusKey  = $this->getKey('status', 'ok');
        $messageKey = $this->getKey('message', 'message');
        $errorsKey  = $this->getKey('errors', 'errors');
        $metaKey    = $this->getKey('meta', 'meta');

        $messageStr = ($message instanceof Stringable || is_scalar($message))
            ? (string) $message
            : $this->getDefaultMessage('bad_request', 'Bad request');

        $payload = [
            $statusKey  => false,
            $messageKey => $messageStr,
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
        mixed $message = null,
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
        mixed $message = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('accepted', 'Request accepted');
        return $this->success($message, $data, Response::HTTP_ACCEPTED, $headers);
    }

    /**
     * 204 No Content Response (Pure empty response conforming to RFC 9110).
     */
    public function noContent(array $headers = []): Response
    {
        $defaultHeaders = (array) config('api-response.headers', []);
        unset($defaultHeaders['Content-Type']);
        $mergedHeaders  = array_merge($defaultHeaders, $this->headers, $headers);

        $response = new Response('', Response::HTTP_NO_CONTENT, $mergedHeaders);

        foreach ($this->cookies as $cookie) {
            $response->withCookie($cookie);
        }

        $this->resetState();

        return $response;
    }

    /**
     * 400 Bad Request Response.
     */
    public function badRequest(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        if ($message !== null && $errors === null && !is_string($message) && !($message instanceof Stringable)) {
            $errors = $message;
            $message = null;
        }
        $message ??= $this->getDefaultMessage('bad_request', 'Bad request');
        return $this->error($message, $errors, Response::HTTP_BAD_REQUEST, $headers);
    }

    /**
     * 401 Unauthorized Response.
     */
    public function unauthorized(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        if ($message !== null && $errors === null && !is_string($message) && !($message instanceof Stringable)) {
            $errors = $message;
            $message = null;
        }
        $message ??= $this->getDefaultMessage('unauthorized', 'Unauthorized access');
        return $this->error($message, $errors, Response::HTTP_UNAUTHORIZED, $headers);
    }

    /**
     * 403 Forbidden Response.
     */
    public function forbidden(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        if ($message !== null && $errors === null && !is_string($message) && !($message instanceof Stringable)) {
            $errors = $message;
            $message = null;
        }
        $message ??= $this->getDefaultMessage('forbidden', 'Access forbidden');
        return $this->error($message, $errors, Response::HTTP_FORBIDDEN, $headers);
    }

    /**
     * 404 Not Found Response.
     */
    public function notFound(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        if ($message !== null && $errors === null && !is_string($message) && !($message instanceof Stringable)) {
            $errors = $message;
            $message = null;
        }
        $message ??= $this->getDefaultMessage('not_found', 'Resource not found');
        return $this->error($message, $errors, Response::HTTP_NOT_FOUND, $headers);
    }

    /**
     * 422 Unprocessable Entity / Validation Error Response.
     */
    public function validationError(
        mixed $errors,
        mixed $message = null,
        array $headers = []
    ): JsonResponse {
        $message ??= $this->getDefaultMessage('validation_error', 'Validation failed');
        return $this->error($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY, $headers);
    }

    /**
     * 500 Internal Server Error Response.
     */
    public function serverError(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        if ($message instanceof Throwable || (is_array($message) && $errors === null)) {
            $errors = $message;
            $message = null;
        }
        $message ??= $this->getDefaultMessage('server_error', 'An internal server error occurred');
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
     * Recursively normalize collection items (preserving associative keys while keeping lists sequential).
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

        return array_is_list($results) ? array_values($results) : $results;
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
                // Strip function args from trace to prevent circular recursion and OOM
                $safeTrace = collect($errors->getTrace())->take(10)->map(function ($frame) {
                    return [
                        'file'     => $frame['file'] ?? '[internal]',
                        'line'     => $frame['line'] ?? 0,
                        'class'    => $frame['class'] ?? null,
                        'function' => $frame['function'] ?? null,
                    ];
                })->all();

                return [
                    'exception' => get_class($errors),
                    'message'   => $errors->getMessage(),
                    'file'      => $errors->getFile(),
                    'line'      => $errors->getLine(),
                    'trace'     => $safeTrace,
                ];
            }

            // In production, prevent leaking internal SQL / Database details
            if ($errors instanceof QueryException || $errors instanceof PDOException) {
                return ['error' => ['A database query error occurred.']];
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
        try {
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

            return $response;
        } finally {
            $this->resetState();
        }
    }

    /**
     * Reset fluent state to prevent leaks in Octane / roadrunner / singletons.
     */
    public function resetState(): void
    {
        $this->meta        = [];
        $this->headers     = [];
        $this->cookies     = [];
        $this->traceId     = null;
        $this->jsonOptions = null;
    }

    /**
     * Resolve or generate a unique Trace ID.
     */
    public function getOrGenerateTraceId(): string
    {
        if ($this->traceId !== null && trim($this->traceId) !== '') {
            return $this->traceId;
        }

        $headerName = (string) config('api-response.trace_id.header_name', 'X-Request-Id');
        $request = request();

        if ($request && $request->hasHeader($headerName)) {
            $val = trim((string) $request->header($headerName));
            if ($val !== '') {
                $this->traceId = $val;
                return $this->traceId;
            }
        }

        $this->traceId = 'req_' . bin2hex(random_bytes(8));
        return $this->traceId;
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
