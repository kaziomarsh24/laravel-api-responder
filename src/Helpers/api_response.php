<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use KaziOmar\ApiResponder\Facades\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

if (! function_exists('response_success')) {
    /**
     * Return a standardized success JSON response.
     */
    function response_success(
        mixed $message = 'Success',
        mixed $data = null,
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::success($message, $data, $statusCode, $headers);
    }
}

if (! function_exists('response_error')) {
    /**
     * Return a standardized error JSON response.
     */
    function response_error(
        mixed $message = 'Error',
        mixed $errors = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::error($message, $errors, $statusCode, $headers);
    }
}

if (! function_exists('response_created')) {
    /**
     * Return a 201 Created JSON response.
     */
    function response_created(
        mixed $data = null,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::created($data, $message, $headers);
    }
}

if (! function_exists('response_accepted')) {
    /**
     * Return a 202 Accepted JSON response.
     */
    function response_accepted(
        mixed $data = null,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::accepted($data, $message, $headers);
    }
}

if (! function_exists('response_no_content')) {
    /**
     * Return a 204 No Content response.
     */
    function response_no_content(array $headers = []): JsonResponse
    {
        return ApiResponse::noContent($headers);
    }
}

if (! function_exists('response_bad_request')) {
    /**
     * Return a 400 Bad Request JSON response.
     */
    function response_bad_request(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::badRequest($message, $errors, $headers);
    }
}

if (! function_exists('response_unauthorized')) {
    /**
     * Return a 401 Unauthorized JSON response.
     */
    function response_unauthorized(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::unauthorized($message, $errors, $headers);
    }
}

if (! function_exists('response_forbidden')) {
    /**
     * Return a 403 Forbidden JSON response.
     */
    function response_forbidden(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::forbidden($message, $errors, $headers);
    }
}

if (! function_exists('response_not_found')) {
    /**
     * Return a 404 Not Found JSON response.
     */
    function response_not_found(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::notFound($message, $errors, $headers);
    }
}

if (! function_exists('response_validation_error')) {
    /**
     * Return a 422 Unprocessable Entity / Validation Error response.
     */
    function response_validation_error(
        mixed $errors,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::validationError($errors, $message, $headers);
    }
}

if (! function_exists('response_server_error')) {
    /**
     * Return a 500 Internal Server Error JSON response.
     */
    function response_server_error(
        ?string $message = null,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return ApiResponse::serverError($message, $errors, $headers);
    }
}
