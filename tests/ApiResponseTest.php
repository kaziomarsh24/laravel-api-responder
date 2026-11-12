<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Tests;

use ArrayIterator;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Stringable;
use JsonSerializable;
use KaziOmar\ApiResponder\Facades\ApiResponse;
use KaziOmar\ApiResponder\Handlers\ApiExceptionHandler;
use KaziOmar\ApiResponder\Middleware\ForceJsonResponse;
use KaziOmar\ApiResponder\Traits\HasApiResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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

class CustomArrayable implements Arrayable
{
    public function __construct(protected array $attributes = ['custom' => 'arrayable_value']) {}

    public function toArray(): array
    {
        return $this->attributes;
    }
}

class CustomJsonSerializable implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['serializable' => 'json_value'];
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

    public function noContentAction(): Response
    {
        return $this->noContent();
    }

    public function chainedMetaAction(): JsonResponse
    {
        return $this->withMeta(['version' => '1.0'])
            ->withHeader('X-Trait-Header', 'Attached')
            ->success('Chained from trait', ['status' => 'ok']);
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

    public function test_stringable_message_support(): void
    {
        $response = ApiResponse::success(new Stringable('Stringable Message'), ['id' => 5]);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals('Stringable Message', $payload['message']);
        $this->assertEquals(['id' => 5], $payload['data']);
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

    public function test_error_with_errors_array_as_first_argument(): void
    {
        // Tests fix for parameter swapping when array passed to error()
        $response = ApiResponse::error(['email' => ['Email required']]);
        $payload = $response->getData(true);

        $this->assertFalse($payload['ok']);
        $this->assertEquals('Bad request', $payload['message']);
        $this->assertEquals(['email' => ['Email required']], $payload['errors']);
    }

    public function test_semantic_helpers_accept_payload_as_first_argument(): void
    {
        // Tests fix for serverError($e) and badRequest($err) without TypeError
        $exception = new Exception('Server broke');
        $serverRes = ApiResponse::serverError($exception);
        $this->assertEquals(500, $serverRes->getStatusCode());
        $this->assertFalse($serverRes->getData(true)['ok']);
        $this->assertEquals('An internal server error occurred', $serverRes->getData(true)['message']);

        $badRes = ApiResponse::badRequest(['name' => ['Name is invalid']]);
        $this->assertEquals(400, $badRes->getStatusCode());
        $this->assertEquals(['name' => ['Name is invalid']], $badRes->getData(true)['errors']);
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

    public function test_query_exception_does_not_leak_sql_in_production(): void
    {
        config(['api-response.debug_exceptions' => false]);
        $queryEx = new QueryException('sqlite', 'SELECT * FROM secret_passwords WHERE id = 1', [], new Exception('Table not found'));

        $response = ApiResponse::serverError('DB Error', $queryEx);
        $payload = $response->getData(true);

        $this->assertFalse($payload['ok']);
        $this->assertEquals(['error' => ['A database query error occurred.']], $payload['errors']);
        $this->assertStringNotContainsString('secret_passwords', json_encode($payload));
    }

    public function test_debug_exceptions_sanitizes_stack_trace_arguments(): void
    {
        config(['api-response.debug_exceptions' => true]);
        $ex = new Exception('Debuggable crash');

        $response = ApiResponse::serverError('Crash', $ex);
        $payload = $response->getData(true);

        $this->assertArrayHasKey('trace', $payload['errors']);
        $this->assertIsArray($payload['errors']['trace']);
        if (!empty($payload['errors']['trace'])) {
            $frame = $payload['errors']['trace'][0];
            $this->assertArrayHasKey('file', $frame);
            $this->assertArrayHasKey('line', $frame);
            $this->assertArrayNotHasKey('args', $frame); // Ensures no recursion args
        }
    }

    public function test_rfc_9110_compliant_204_no_content(): void
    {
        $response = ApiResponse::noContent(['X-Custom' => 'Done']);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
        $this->assertEquals('Done', $response->headers->get('X-Custom'));
        $this->assertNull($response->headers->get('Content-Type'));
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

        $paginator = new LengthAwarePaginator($items, 100, 2, 1);

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

    public function test_empty_paginator_handling(): void
    {
        $emptyPaginator = new LengthAwarePaginator(collect([]), 0, 15, 1);

        $response = ApiResponse::success('Empty records', $emptyPaginator);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals([], $payload['data']);
        $this->assertEquals(0, $payload['pagination']['total']);
        $this->assertEquals(0, $payload['pagination']['count']);
        $this->assertFalse($payload['pagination']['has_more']);
    }

    public function test_associative_collection_keys_are_preserved(): void
    {
        $keyedData = collect([
            'slug-one' => ['title' => 'First Post'],
            'slug-two' => ['title' => 'Second Post'],
        ]);

        $response = ApiResponse::success('Keyed items', $keyedData);
        $payload = $response->getData(true);

        $this->assertArrayHasKey('slug-one', $payload['data']);
        $this->assertArrayHasKey('slug-two', $payload['data']);
    }

    public function test_arrayable_json_serializable_and_traversable_data(): void
    {
        $arrayableRes = ApiResponse::success('Arrayable', new CustomArrayable());
        $this->assertEquals(['custom' => 'arrayable_value'], $arrayableRes->getData(true)['data']);

        $serializableRes = ApiResponse::success('Serializable', new CustomJsonSerializable());
        $this->assertEquals(['serializable' => 'json_value'], $serializableRes->getData(true)['data']);

        $iteratorRes = ApiResponse::success('Iterator', new ArrayIterator(['item1', 'item2']));
        $this->assertEquals(['item1', 'item2'], $iteratorRes->getData(true)['data']);
    }

    public function test_cursor_paginator_with_null_and_present_cursors(): void
    {
        $items = collect([['id' => 10, 'title' => 'Post 10'], ['id' => 11, 'title' => 'Post 11']]);
        $paginator = new CursorPaginator($items, 1, null, ['parameters' => ['id']]);

        $response = ApiResponse::success('Cursor fetched', $paginator);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals(1, $payload['pagination']['count']);
        $this->assertTrue($payload['pagination']['has_more']);
        $this->assertNotNull($payload['pagination']['next_cursor']);
    }

    public function test_trace_id_memoization_and_header_reuse(): void
    {
        $trace1 = ApiResponse::getOrGenerateTraceId();
        $trace2 = ApiResponse::getOrGenerateTraceId();
        $this->assertEquals($trace1, $trace2);

        $customTraceRes = ApiResponse::make()
            ->withTraceId('static_trace_12345')
            ->success('With static trace');

        $this->assertEquals('static_trace_12345', $customTraceRes->headers->get('X-Request-Id'));
        $this->assertEquals('static_trace_12345', $customTraceRes->getData(true)['trace_id']);
    }

    public function test_state_does_not_leak_across_consecutive_calls(): void
    {
        // Call 1 with custom meta and headers
        ApiResponse::make()
            ->withMeta(['leaked' => true])
            ->withHeader('X-Leaked', 'Yes')
            ->success('Call 1');

        // Call 2 fresh
        $cleanRes = ApiResponse::success('Call 2');
        $this->assertArrayNotHasKey('meta', $cleanRes->getData(true));
        $this->assertNull($cleanRes->headers->get('X-Leaked'));
    }

    public function test_trait_fluent_chaining(): void
    {
        $controller = new DummyController();
        $response = $controller->chainedMetaAction();

        $this->assertEquals('Attached', $response->headers->get('X-Trait-Header'));
        $payload = $response->getData(true);
        $this->assertEquals(['version' => '1.0'], $payload['meta']);
    }

    public function test_global_exception_handler_model_not_found_with_composite_ids(): void
    {
        $exception = new ModelNotFoundException();
        $exception->setModel('App\\Models\\OrderItem', [10, 20]);

        $response = ApiExceptionHandler::render($exception);
        $this->assertEquals(404, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertStringContainsString('OrderItem with ID [10, 20] not found', $payload['errors']['error'][0]);
    }

    public function test_global_exception_handler_http_exception_types(): void
    {
        $denied = new AccessDeniedHttpException('Policy rejection');
        $resDenied = ApiExceptionHandler::render($denied);
        $this->assertEquals(403, $resDenied->getStatusCode());

        $methodNotAllowed = new MethodNotAllowedHttpException(['POST'], 'Method not allowed');
        $resMethod = ApiExceptionHandler::render($methodNotAllowed);
        $this->assertEquals(405, $resMethod->getStatusCode());

        $tooMany = new TooManyRequestsHttpException(60, 'Throttled');
        $resTooMany = ApiExceptionHandler::render($tooMany);
        $this->assertEquals(429, $resTooMany->getStatusCode());
    }

    public function test_global_helpers_coverage(): void
    {
        $resCreated = response_created(['id' => 1], 'Created');
        $this->assertEquals(201, $resCreated->getStatusCode());

        $resAccepted = response_accepted(['queued' => true], 'Accepted');
        $this->assertEquals(202, $resAccepted->getStatusCode());

        $resNoContent = response_no_content();
        $this->assertEquals(204, $resNoContent->getStatusCode());
        $this->assertEquals('', $resNoContent->getContent());

        $resNotFound = response_not_found('User missing');
        $this->assertEquals(404, $resNotFound->getStatusCode());

        $resUnauthorized = response_unauthorized('Please login');
        $this->assertEquals(401, $resUnauthorized->getStatusCode());

        $resForbidden = response_forbidden('Denied');
        $this->assertEquals(403, $resForbidden->getStatusCode());

        $resBadRequest = response_bad_request('Malformed');
        $this->assertEquals(400, $resBadRequest->getStatusCode());

        $resServer = response_server_error('Crash');
        $this->assertEquals(500, $resServer->getStatusCode());
    }

    public function test_force_json_response_middleware(): void
    {
        $middleware = new ForceJsonResponse();
        $request = \Illuminate\Http\Request::create('/api/test', 'GET');
        $this->assertFalse($request->expectsJson());

        $middleware->handle($request, function ($req) {
            $this->assertTrue($req->expectsJson());
            return response()->json(['ok' => true]);
        });
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

    public function test_automatic_exception_handling_via_http_pipeline(): void
    {
        $this->app['router']->get('/api/test-fail', function () {
            $exception = new ModelNotFoundException();
            $exception->setModel('App\\Models\\User', [999]);
            throw $exception;
        });

        $response = $this->getJson('/api/test-fail');

        $response->assertStatus(404);
        $payload = $response->json();
        $this->assertFalse($payload['ok']);
        $this->assertEquals('Resource not found', $payload['message']);
        $this->assertStringContainsString('User with ID [999] not found', $payload['errors']['error'][0]);
        $this->assertArrayHasKey('trace_id', $payload);
    }
}
