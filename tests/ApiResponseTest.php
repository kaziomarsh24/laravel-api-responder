<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Tests;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use KaziOmar\ApiResponder\Facades\ApiResponse;
use KaziOmar\ApiResponder\Traits\HasApiResponse;

class DummyUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->resource['id'],
            'name'  => strtoupper($this->resource['name']),
            'email' => $this->resource['email'],
        ];
    }
}

class DummyController
{
    use HasApiResponse;

    public function index(): JsonResponse
    {
        return $this->success('Fetched successfully', ['id' => 1]);
    }

    public function create(): JsonResponse
    {
        return $this->created(['id' => 2], 'Created user');
    }

    public function failed(): JsonResponse
    {
        return $this->error('Failed action', ['reason' => 'Invalid']);
    }

    public function notFoundAction(): JsonResponse
    {
        return $this->notFound('Item not found');
    }

    public function validateAction($validator): JsonResponse
    {
        return $this->validationError($validator, 'Check your input');
    }
}

class ApiResponseTest extends TestCase
{
    public function test_standard_success_envelope(): void
    {
        $response = ApiResponse::success('User retrieved', ['id' => 10, 'name' => 'Kazi Omar']);
        $this->assertEquals(200, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertTrue($payload['ok']);
        $this->assertEquals('User retrieved', $payload['message']);
        $this->assertEquals(['id' => 10, 'name' => 'Kazi Omar'], $payload['data']);
        $this->assertArrayNotHasKey('pagination', $payload);
    }

    public function test_ergonomic_data_as_first_argument(): void
    {
        $response = ApiResponse::success(['id' => 10, 'role' => 'admin']);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals('Success', $payload['message']);
        $this->assertEquals(['id' => 10, 'role' => 'admin'], $payload['data']);
    }

    public function test_standard_error_envelope(): void
    {
        $response = ApiResponse::error('Something went wrong', ['code' => 'ERR_001'], 400);
        $this->assertEquals(400, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertEquals('Something went wrong', $payload['message']);
        $this->assertEquals(['code' => 'ERR_001'], $payload['errors']);
    }

    public function test_error_with_plain_string_normalizes_cleanly(): void
    {
        $response = ApiResponse::error('Unauthorized request', 'Invalid Bearer token', 401);
        $payload = $response->getData(true);

        $this->assertFalse($payload['ok']);
        $this->assertEquals(['error' => ['Invalid Bearer token']], $payload['errors']);
    }

    public function test_error_with_validator_instance(): void
    {
        $validator = Validator::make(['email' => 'invalid-email'], [
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        $this->assertTrue($validator->fails());

        $response = ApiResponse::validationError($validator);
        $this->assertEquals(422, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertEquals('Validation failed', $payload['message']);
        $this->assertArrayHasKey('email', $payload['errors']);
        $this->assertArrayHasKey('password', $payload['errors']);
    }

    public function test_error_with_throwable_gracefully_handled(): void
    {
        $exception = new Exception('Database timeout error', 500);
        $response = ApiResponse::serverError('Server crashed', $exception);

        $this->assertEquals(500, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertEquals('Server crashed', $payload['message']);
        $this->assertArrayHasKey('errors', $payload);
    }

    public function test_json_resource_resolution(): void
    {
        $resource = new DummyUserResource([
            'id'    => 1,
            'name'  => 'kazi omar faruk',
            'email' => 'softeng.kaziomar@gmail.com',
        ]);

        $response = ApiResponse::success('User profile', $resource);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals([
            'id'    => 1,
            'name'  => 'KAZI OMAR FARUK',
            'email' => 'softeng.kaziomar@gmail.com',
        ], $payload['data']);
    }

    public function test_length_aware_paginator_extraction(): void
    {
        $items = collect([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $paginator = new LengthAwarePaginator(
            $items,
            100, // total
            2,   // per page
            1    // current page
        );

        $response = ApiResponse::success('User list', $paginator);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertCount(2, $payload['data']);
        $this->assertEquals([
            'total'        => 100,
            'count'        => 2,
            'per_page'     => 2,
            'current_page' => 1,
            'total_pages'  => 50,
            'has_more'     => true,
        ], $payload['pagination']);
    }

    public function test_resource_collection_with_paginator(): void
    {
        $rawUsers = collect([
            ['id' => 1, 'name' => 'kazi', 'email' => 'kazi@example.com'],
            ['id' => 2, 'name' => 'omar', 'email' => 'omar@example.com'],
        ]);

        $paginator = new LengthAwarePaginator($rawUsers, 50, 2, 1);
        $resourceCollection = DummyUserResource::collection($paginator);

        $response = ApiResponse::success('Fetched users', $resourceCollection);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals('KAZI', $payload['data'][0]['name']);
        $this->assertEquals('OMAR', $payload['data'][1]['name']);
        $this->assertEquals(50, $payload['pagination']['total']);
        $this->assertEquals(2, $payload['pagination']['per_page']);
        $this->assertEquals(1, $payload['pagination']['current_page']);
    }

    public function test_cursor_paginator_extraction(): void
    {
        $items = collect([
            ['id' => 10, 'title' => 'Post 10'],
        ]);

        $paginator = new CursorPaginator(
            $items,
            1,
            new Cursor(['id' => 10])
        );

        $response = ApiResponse::success('Cursor fetched', $paginator);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertArrayHasKey('pagination', $payload);
        $this->assertEquals(1, $payload['pagination']['count']);
        $this->assertEquals(1, $payload['pagination']['per_page']);
    }

    public function test_configurable_envelope_keys(): void
    {
        config([
            'api-response.keys.status'     => 'success',
            'api-response.keys.message'    => 'msg',
            'api-response.keys.data'       => 'payload',
            'api-response.keys.pagination' => 'page_info',
            'api-response.pagination.total'=> 'records_count',
        ]);

        $paginator = new LengthAwarePaginator(collect([['id' => 1]]), 10, 1, 1);

        $response = ApiResponse::success('All good', $paginator);
        $data = $response->getData(true);

        $this->assertArrayHasKey('success', $data);
        $this->assertArrayNotHasKey('ok', $data);
        $this->assertArrayHasKey('msg', $data);
        $this->assertArrayHasKey('payload', $data);
        $this->assertArrayHasKey('page_info', $data);
        $this->assertEquals(10, $data['page_info']['records_count']);
    }

    public function test_fluent_with_meta_and_headers(): void
    {
        $response = ApiResponse::make()
            ->withMeta(['server_time' => '2026-11-12T14:43:00Z'])
            ->withHeader('X-Custom-Header', 'ApiResponsePackage')
            ->success('Success with meta', ['key' => 'val']);

        $this->assertEquals('ApiResponsePackage', $response->headers->get('X-Custom-Header'));

        $payload = $response->getData(true);
        $this->assertEquals(['server_time' => '2026-11-12T14:43:00Z'], $payload['meta']);
    }

    public function test_controller_trait_integration(): void
    {
        $controller = new DummyController();

        $indexRes = $controller->index();
        $this->assertEquals(200, $indexRes->getStatusCode());
        $this->assertTrue($indexRes->getData(true)['ok']);

        $createRes = $controller->create();
        $this->assertEquals(201, $createRes->getStatusCode());
        $this->assertEquals('Created user', $createRes->getData(true)['message']);

        $failRes = $controller->failed();
        $this->assertEquals(400, $failRes->getStatusCode());
        $this->assertFalse($failRes->getData(true)['ok']);

        $notFoundRes = $controller->notFoundAction();
        $this->assertEquals(404, $notFoundRes->getStatusCode());
    }

    public function test_global_helper_functions(): void
    {
        $resSuccess = response_success('Helper success', ['status' => 'active']);
        $this->assertEquals(200, $resSuccess->getStatusCode());
        $this->assertTrue($resSuccess->getData(true)['ok']);

        $resCreated = response_created(['new_id' => 99], 'Item created');
        $this->assertEquals(201, $resCreated->getStatusCode());
        $this->assertEquals(['new_id' => 99], $resCreated->getData(true)['data']);

        $resError = response_error('Bad input', ['field' => ['Invalid']]);
        $this->assertEquals(400, $resError->getStatusCode());

        $resNotFound = response_not_found('User missing');
        $this->assertEquals(404, $resNotFound->getStatusCode());

        $resUnauthorized = response_unauthorized('Please login');
        $this->assertEquals(401, $resUnauthorized->getStatusCode());

        $resForbidden = response_forbidden('Denied');
        $this->assertEquals(403, $resForbidden->getStatusCode());

        $resNoContent = response_no_content();
        $this->assertEquals(204, $resNoContent->getStatusCode());

        $resBadRequest = response_bad_request('Malformed request');
        $this->assertEquals(400, $resBadRequest->getStatusCode());

        $resServer = response_server_error('Crash');
        $this->assertEquals(500, $resServer->getStatusCode());
    }

    public function test_simple_paginator_extraction(): void
    {
        $items = collect([['id' => 1], ['id' => 2]]);
        $paginator = new Paginator($items, 2, 1);

        $response = ApiResponse::success('Simple paginated', $paginator);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertArrayHasKey('pagination', $payload);
        $this->assertEquals(2, $payload['pagination']['count']);
        $this->assertEquals(2, $payload['pagination']['per_page']);
        $this->assertEquals(1, $payload['pagination']['current_page']);
        $this->assertFalse($payload['pagination']['has_more']);
    }

    public function test_message_bag_error_normalization(): void
    {
        $bag = new \Illuminate\Support\MessageBag([
            'username' => ['Username is already taken.'],
        ]);

        $response = ApiResponse::error('Validation issue', $bag, 422);
        $payload = $response->getData(true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertFalse($payload['ok']);
        $this->assertEquals(['username' => ['Username is already taken.']], $payload['errors']);
    }

    public function test_include_null_data_configuration(): void
    {
        config(['api-response.include_null_data' => false]);
        $resWithoutData = ApiResponse::success('Action completed', null);
        $this->assertArrayNotHasKey('data', $resWithoutData->getData(true));

        config(['api-response.include_null_data' => true]);
        $resWithNullData = ApiResponse::success('Action completed', null);
        $this->assertArrayHasKey('data', $resWithNullData->getData(true));
        $this->assertNull($resWithNullData->getData(true)['data']);
    }

    public function test_with_cookie_and_json_options(): void
    {
        $cookie = cookie('session_test', '12345', 60);

        $response = ApiResponse::make()
            ->withCookie($cookie)
            ->setJsonOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->success('Success with cookie', ['unicode' => 'বাংলা']);

        $this->assertStringContainsString('session_test', (string) $response->headers->get('Set-Cookie'));
        $this->assertStringContainsString('বাংলা', $response->getContent());
    }

    public function test_trace_id_is_attached_to_response_and_header(): void
    {
        $response = ApiResponse::success('Tracked action', ['id' => 1]);

        $this->assertTrue($response->headers->has('X-Request-Id'));
        $traceId = $response->headers->get('X-Request-Id');
        $this->assertNotEmpty($traceId);

        $payload = $response->getData(true);
        $this->assertArrayHasKey('trace_id', $payload);
        $this->assertEquals($traceId, $payload['trace_id']);
    }

    public function test_custom_trace_id_can_be_assigned(): void
    {
        $response = ApiResponse::make()
            ->withTraceId('custom_trace_999')
            ->error('Trace failure');

        $this->assertEquals('custom_trace_999', $response->headers->get('X-Request-Id'));
        $this->assertEquals('custom_trace_999', $response->getData(true)['trace_id']);
    }

    public function test_exception_handler_converts_validation_exception(): void
    {
        $validator = Validator::make([], ['title' => 'required']);
        $exception = new \Illuminate\Validation\ValidationException($validator);

        $response = \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::render($exception);
        $this->assertNotNull($response);
        $this->assertEquals(422, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertArrayHasKey('title', $payload['errors']);
    }

    public function test_exception_handler_converts_model_not_found_exception(): void
    {
        $exception = new \Illuminate\Database\Eloquent\ModelNotFoundException();
        $exception->setModel('App\\Models\\User', [42]);

        $response = \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::render($exception);
        $this->assertNotNull($response);
        $this->assertEquals(404, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertStringContainsString('User with ID [42] not found', $payload['errors']['error'][0]);
    }

    public function test_exception_handler_converts_authentication_exception(): void
    {
        $exception = new \Illuminate\Auth\AuthenticationException('Unauthenticated.');

        $response = \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::render($exception);
        $this->assertNotNull($response);
        $this->assertEquals(401, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertEquals('Unauthorized access', $payload['message']);
    }

    public function test_exception_handler_converts_authorization_exception(): void
    {
        $exception = new \Illuminate\Auth\Access\AuthorizationException('Forbidden action.');

        $response = \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::render($exception);
        $this->assertNotNull($response);
        $this->assertEquals(403, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertEquals('Access forbidden', $payload['message']);
    }

    public function test_exception_handler_converts_rate_limit_exception(): void
    {
        $exception = new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(60, 'Too many requests.');

        $response = \KaziOmar\ApiResponder\Handlers\ApiExceptionHandler::render($exception);
        $this->assertNotNull($response);
        $this->assertEquals(429, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['ok']);
        $this->assertEquals('Too many requests. Please slow down.', $payload['message']);
    }

    public function test_force_json_response_middleware(): void
    {
        $middleware = new \KaziOmar\ApiResponder\Middleware\ForceJsonResponse();
        $request = \Illuminate\Http\Request::create('/api/test', 'GET');
        $this->assertFalse($request->expectsJson());

        $middleware->handle($request, function ($req) {
            $this->assertTrue($req->expectsJson());
            return response()->json(['ok' => true]);
        });
    }
}
