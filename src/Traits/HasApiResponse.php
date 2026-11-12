<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Traits;

use Illuminate\Http\JsonResponse;
use KaziOmar\ApiResponder\Facades\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

trait HasApiResponse
{
    /**
     * Attach custom metadata to the response.
     */
    public function withMeta(array $meta): static
    {
        ApiResponse::withMeta($meta);
        return $this;
    }

    /**
     * Add a single HTTP header to the response.
     */
    public function withHeader(string $key, string $value): static
    {
        ApiResponse::withHeader($key, $value);
        return $this;
    }

    /**
     * Add multiple HTTP headers to the response.
     */
    public function withHeaders(array $headers): static
    {
        ApiResponse::withHeaders($headers);
        return $this;
    }

    /**
     * Attach a cookie to the response.
     */
    public function withCookie(mixed $cookie): static
    {
        ApiResponse::withCookie($cookie);
        return $this;
    }

    /**
     * Set a custom trace ID for the response.
     */
    public function withTraceId(string $traceId): static
    {
        ApiResponse::withTraceId($traceId);
        return $this;
    }

    /**
     * Respond with a standardized success envelope.
     */
    public function success(
        mixed $message = null,
        mixed $data = null,
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::success($message, $data, $statusCode, $headers);
    }

    /**
     * Respond with a standardized error envelope.
     */
    public function error(
        mixed $message = null,
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::error($message, $errors, $statusCode, $headers);
    }

    /**
     * 201 Created Response.
     */
    public function created(
        mixed $data = null,
        mixed $message = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::created($data, $message, $headers);
    }

    /**
     * 202 Accepted Response.
     */
    public function accepted(
        mixed $data = null,
        mixed $message = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::accepted($data, $message, $headers);
    }

    /**
     * 204 No Content Response.
     */
    public function noContent(array $headers = []): Response
    {
        return ApiResponse::noContent($headers);
    }

    /**
     * 400 Bad Request Response.
     */
    public function badRequest(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::badRequest($message, $errors, $headers);
    }

    /**
     * 401 Unauthorized Response.
     */
    public function unauthorized(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::unauthorized($message, $errors, $headers);
    }

    /**
     * 403 Forbidden Response.
     */
    public function forbidden(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::forbidden($message, $errors, $headers);
    }

    /**
     * 404 Not Found Response.
     */
    public function notFound(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::notFound($message, $errors, $headers);
    }

    /**
     * 422 Validation Error Response.
     */
    public function validationError(
        mixed $errors,
        mixed $message = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::validationError($errors, $message, $headers);
    }

    /**
     * 500 Internal Server Error Response.
     */
    public function serverError(
        mixed $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::serverError($message, $errors, $headers);
    }
}
