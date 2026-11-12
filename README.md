# Laravel API Responder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/thekaziomar/laravel-api-responder.svg?style=flat-square)](https://packagist.org/packages/thekaziomar/laravel-api-responder)
[![Total Downloads](https://img.shields.io/packagist/dt/thekaziomar/laravel-api-responder.svg?style=flat-square)](https://packagist.org/packages/thekaziomar/laravel-api-responder)
[![Run Tests](https://github.com/thekaziomar/laravel-api-responder/actions/workflows/run-tests.yml/badge.svg)](https://github.com/thekaziomar/laravel-api-responder/actions)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20to%208.5+-777BB4.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-ff2d20.svg?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/github/license/thekaziomar/laravel-api-responder.svg?style=flat-square)](LICENSE)

> ### Developer-First, Production-Grade JSON API Response & Exception Engine for Modern Laravel (11.x, 12.x, 13+)
> Eliminates repetitive `response()->json()` boilerplate, guarantees a predictable JSON envelope for frontend clients (Flutter, React, Vue, iOS, Android), automatically isolates pagination metadata from `JsonResource` collections, catches all unhandled framework exceptions with zero configuration, and tracks every error via unique `trace_id` correlation.

```php
use App\Models\User;
use App\Http\Resources\UserResource;

// 1. Success: Automatically isolates pagination from resource items
return response_success('Users fetched', UserResource::collection(User::paginate(15)));

// 2. Error: Type-safe, accepts strings, arrays, Validators, or Exceptions
return response_error('Payment failed', ['reason' => 'Insufficient funds'], 400);

// 3. Zero-Config Global Exceptions: ModelNotFound (404), FormRequest (422), Auth (401),
// Rate Limit (429), and 500 crashes are automatically converted without touching controllers!
```

---

## 📑 Table of Contents

- [Why Laravel API Responder?](#why-laravel-api-responder)
- [Key Features](#key-features)
- [Requirements & Installation](#requirements--installation)
- [How to Use: The Easy Way vs. The Best-Practice Way](#how-to-use-the-easy-way-vs-the-best-practice-way)
  - [⚡ The Easy Way (Just 2 Global Helper Functions)](#-the-easy-way-just-2-global-helper-functions)
  - [🏆 The Best-Practice Way (Controller Trait & Facade)](#-the-best-practice-way-controller-trait--facade)
- [Smart Automatic Pagination & Resource Extraction](#smart-automatic-pagination--resource-extraction)
  - [LengthAwarePaginator & ResourceCollection](#1-lengthawarepaginator--resourcecollection)
  - [CursorPaginator (Infinite Scroll / Social Feeds)](#2-cursorpaginator-infinite-scroll--social-feeds)
  - [Single JsonResource Resolution](#3-single-jsonresource-resolution)
- [Zero-Config Global Exception Handling](#zero-config-global-exception-handling)
  - [Exceptions Handled Out of the Box](#exceptions-handled-out-of-the-box)
  - [How FormRequest Validation Becomes Seamless](#how-formrequest-validation-becomes-seamless)
- [Production Debugging & Trace ID Tracking](#production-debugging--trace-id-tracking)
  - [Frontend Error Handling Integration](#frontend-error-handling-integration)
  - [Backend Log Correlation](#backend-log-correlation)
  - [Safe Production Mode (`APP_DEBUG=false`) vs Dev Mode (`APP_DEBUG=true`)](#safe-production-mode-app_debugfalse-vs-dev-mode-app_debugtrue)
- [Advanced Features & Fluent Chaining](#advanced-features--fluent-chaining)
  - [Attaching Custom Metadata (`withMeta`)](#attaching-custom-metadata-withmeta)
  - [Custom Headers & Cookies](#custom-headers--cookies)
  - [Force JSON Middleware](#force-json-middleware)
- [Comprehensive Method & Status Code Matrix](#comprehensive-method--status-code-matrix)
- [Configuration Reference (`config/api-response.php`)](#configuration-reference-configapi-responsephp)
- [Local Package Development Setup](#local-package-development-setup)
- [Testing](#testing)
- [License & Credits](#license--credits)

---

## 🎯 Why Laravel API Responder?

When building RESTful APIs in Laravel, developers commonly face three major headaches:

| Common Pain Point | Traditional Laravel Workflow | With Laravel API Responder |
| :--- | :--- | :--- |
| **Inconsistent Response Envelopes** | Team members invent their own keys (`{"status": "ok"}`, `{"success": true}`, `{"result": ...}`). Frontend clients break frequently. | Every endpoint follows a uniform contract: `{ "ok": true, "message": "...", "data": ... }` or `{ "ok": false, "message": "...", "errors": ... }`. |
| **Messy Pagination Metadata** | Default `User::paginate()` mixes `links`, `path`, and raw items into a tangled structure that is painful for Flutter/React tables. | Items stay clean in `data`, while `total`, `per_page`, `current_page`, and `has_more` cleanly isolate into root-level `pagination`. |
| **Unhandled Framework Exceptions** | `Model::findOrFail()` or FormRequest throws default HTML or raw unformatted JSON that violates your API envelope. | Automatically intercepted in the background and converted into standardized JSON errors with zero controller code. |
| **Production Support & Debugging** | Users report "An error occurred", but finding the exact trace in gigabytes of logs takes hours. | Injects a unique `trace_id` (e.g. `req_a4f91b72e18d6a3c`) into the response and HTTP header for instant `grep` correlation. |

---

## 🚀 Key Features

- **Strict Uniform Envelope**: Guaranteed predictable structure across every endpoint.
- **Smart Data Unwrapping**: Automatically detects and extracts `ResourceCollection`, `JsonResource`, `LengthAwarePaginator`, `CursorPaginator`, `SimplePaginator`, `Collection`, `Arrayable`, and raw arrays.
- **Zero-Config Global Exception Handling**: Auto-catches `ValidationException`, `ModelNotFoundException`, `AuthenticationException`, `AuthorizationException`, `TooManyRequestsHttpException`, `NotFoundHttpException`, and 500 crashes.
- **Trace ID Correlation**: Automatically sets `X-Request-Id` and embeds `trace_id` for instant production log matching.
- **Force JSON Middleware**: Stops Laravel from redirecting API requests to web login routes when clients omit the `Accept: application/json` header.
- **Ergonomic Swapping**: Pass data directly as the first argument (`response_success($data)`) without being forced to type a dummy message.
- **Tailored for Modern Laravel**: Built natively for Laravel 11.x, 12.x, and 13+ with PHP 8.2 to 8.5+.

---

## 📦 Requirements & Installation

- **PHP**: `^8.2` or higher (including PHP 8.3, 8.4, and 8.5+)
- **Laravel**: `^11.0`, `^12.0`, or `^13.0`

Install the package via Composer:

```bash
composer require thekaziomar/laravel-api-responder
```

The Service Provider and `ApiResponse` Facade will automatically register via Laravel's Package Auto-Discovery.

### Publish Configuration (Optional)

If you wish to customize envelope keys (e.g., renaming `ok` to `success` or `data` to `payload`):

```bash
php artisan vendor:publish --tag="api-responder-config"
```

---

## 💡 How to Use: The Easy Way vs. The Best-Practice Way

You have complete flexibility to pick the workflow that best fits your style.

---

### ⚡ The Easy Way (Just 2 Global Helper Functions)

If you don't want to memorize 15 different function names, **you only need to remember 2 functions for your entire project**:

#### 1. For All Success Scenarios (`response_success`):
```php
// Standard single item
return response_success('User loaded', $user);

// Direct data (message defaults to 'Success')
return response_success($user);

// Paginated collections (automatically extracts pagination!)
return response_success('Users list', User::paginate(15));

// Transformed API Resources
return response_success('Profile', new UserResource($user));

// 201 Created with status code override
return response_success('User created', $newUser, 201);
```

#### 2. For All Error Scenarios (`response_error`):
```php
// Simple string error
return response_error('Invalid credentials', null, 401);

// Key-value error bag
return response_error('Checkout failed', ['balance' => ['Insufficient funds']], 400);

// With custom status code (e.g. 403 Forbidden, 404 Not Found)
return response_error('Resource missing', ['id' => ['User not found']], 404);
```

---

### 🏆 The Best-Practice Way (Controller Trait & Facade)

For larger teams and domain-driven architectures, use the `HasApiResponse` trait inside your Base Controller or specific API Controllers.

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use KaziOmar\ApiResponder\Traits\HasApiResponse;

class ProductController extends Controller
{
    use HasApiResponse;

    /**
     * GET /api/products
     * Automatically extracts pagination metadata.
     */
    public function index()
    {
        $products = Product::latest()->paginate(20);
        return $this->success('Products retrieved', ProductResource::collection($products));
    }

    /**
     * POST /api/products
     * FormRequest validation errors are auto-handled; returns 201 Created.
     */
    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());
        return $this->created(new ProductResource($product), 'Product created successfully');
    }

    /**
     * GET /api/products/{id}
     * ModelNotFoundException is auto-handled if using findOrFail.
     */
    public function show($id)
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->notFound('Product not found');
        }

        return $this->success('Product details', new ProductResource($product));
    }

    /**
     * DELETE /api/products/{id}
     * Returns 204 No Content.
     */
    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
        return $this->noContent();
    }
}
```

---

## 📊 Smart Automatic Pagination & Resource Extraction

When you return paginated data in Laravel, our package automatically detects the paginator, extracts the items, and isolates the pagination details into a clean root-level object.

### 1. `LengthAwarePaginator` & `ResourceCollection`

```php
return response_success('Users fetched', UserResource::collection(User::paginate(20)));
```

**JSON Output:**
```json
{
  "ok": true,
  "message": "Users fetched",
  "data": [
    {
      "id": 1,
      "name": "Kazi Omar Faruk",
      "email": "softeng.kaziomar@gmail.com"
    },
    {
      "id": 2,
      "name": "Rahim Ahmed",
      "email": "rahim@example.com"
    }
  ],
  "pagination": {
    "total": 100,
    "count": 20,
    "per_page": 20,
    "current_page": 1,
    "total_pages": 5,
    "has_more": true
  },
  "trace_id": "req_5f8a1b2c3d4e6f7a"
}
```

### 2. `CursorPaginator` (Infinite Scroll / Social Feeds)

```php
return response_success('Timeline stream', Post::cursorPaginate(25));
```

**JSON Output:**
```json
{
  "ok": true,
  "message": "Timeline stream",
  "data": [ ... ],
  "pagination": {
    "count": 25,
    "per_page": 25,
    "has_more": true,
    "next_cursor": "eyJpZCI6MjV9",
    "prev_cursor": null
  },
  "trace_id": "req_7a8b9c0d1e2f3a4b"
}
```

### 3. Single `JsonResource` Resolution

```php
return response_success('Profile data', new UserResource($user));
```

Resolves automatically via `$resource->resolve()`, stripping out internal Laravel resource wrapper artifacts.

---

## 🛡️ Zero-Config Global Exception Handling

Normally, unhandled exceptions in Laravel return non-uniform error formats or HTML pages. With `laravel-api-responder`, **all framework exceptions are automatically converted into uniform JSON error envelopes** without writing a single `try-catch` block!

### Exceptions Handled Out of the Box:

| Exception Type | Trigger Scenario | HTTP Status | Response Format |
| :--- | :--- | :--- | :--- |
| **`ValidationException`** | FormRequest or manual `$validator->fails()` | `422` | `{ "ok": false, "message": "Validation failed", "errors": { "field": ["Error"] } }` |
| **`ModelNotFoundException`** | `User::findOrFail(999)` | `404` | `{ "ok": false, "message": "Resource not found", "errors": { "error": ["User with ID [999] not found."] } }` |
| **`NotFoundHttpException`** | Invalid API URL / route | `404` | `{ "ok": false, "message": "Resource not found", "errors": { "error": ["Route does not exist."] } }` |
| **`AuthenticationException`** | Missing or expired Bearer token | `401` | `{ "ok": false, "message": "Unauthorized access", "errors": { "error": ["Unauthenticated."] } }` |
| **`AuthorizationException`** | Gate / Policy denial (`$user->can()`) | `403` | `{ "ok": false, "message": "Access forbidden", "errors": { "error": ["Permission denied."] } }` |
| **`TooManyRequestsHttpException`** | Rate limiter exceeded (`throttle:api`) | `429` | `{ "ok": false, "message": "Too many requests. Please slow down.", "errors": { ... } }` |
| **`MethodNotAllowedHttpException`** | Calling `GET` on a `POST` route | `405` | `{ "ok": false, "message": "Method not allowed", "errors": { ... } }` |
| **`Throwable` / Uncaught Crash** | Database deadlocks, syntax errors, 500s | `500` | Sanitized message in production; rich stack trace in local environment. |

### How FormRequest Validation Becomes Seamless

You no longer need to override `failedValidation()` inside your FormRequest classes. Simply use standard FormRequests:

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ];
    }
}
```

When validation fails, our package automatically formats it into:
```json
{
  "ok": false,
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field must be a valid email address."
    ],
    "password": [
      "The password field must be at least 8 characters."
    ]
  },
  "trace_id": "req_8c9d0e1f2a3b4c5d"
}
```

---

## 🔍 Production Debugging & Trace ID Tracking

Every response (both success and error) automatically includes a unique `trace_id` and sets the `X-Request-Id` HTTP response header.

### Frontend Error Handling Integration

Your frontend team (Axios, Fetch, or Flutter `Dio`) can set up a single global interceptor:

```javascript
// axios instance
apiClient.interceptors.response.use(
  (response) => response.data,
  (error) => {
    const apiError = error.response?.data;

    // 1. Show user-friendly alert
    alert(apiError.message || 'Something went wrong');

    // 2. Log trace ID for customer support
    console.error(`Support Reference ID: ${apiError.trace_id}`);

    return Promise.reject(apiError);
  }
);
```

### Backend Log Correlation

When a user submits a support ticket stating:
> *"My payment failed with reference ID `req_8c9d0e1f2a3b4c5d`"*

Simply run:
```bash
grep "req_8c9d0e1f2a3b4c5d" storage/logs/laravel.log
```
You can instantly pinpoint the exact stack trace, query bindings, and request parameters!

### Safe Production Mode (`APP_DEBUG=false`) vs Dev Mode (`APP_DEBUG=true`)

- **In Production (`APP_DEBUG=false`)**: Sensible generic messages are returned. No database credentials, SQL queries, or file paths are ever leaked to the client.
- **In Local Development (`APP_DEBUG=true`)**: The `errors` payload expands with `exception`, `file`, `line`, and the first 10 trace frames so you can debug directly in Postman without opening log files:

```json
{
  "ok": false,
  "message": "An internal server error occurred",
  "errors": {
    "exception": "DivisionByZeroError",
    "message": "Division by zero",
    "file": "/app/Http/Controllers/Api/OrderController.php",
    "line": 42,
    "trace": [ ... ]
  },
  "trace_id": "req_9988776655443322"
}
```

---

## ⚡ Advanced Features & Fluent Chaining

### Attaching Custom Metadata (`withMeta`)

Need to pass execution metrics, API version numbers, or server timestamps?

```php
use KaziOmar\ApiResponder\Facades\ApiResponse;

return ApiResponse::make()
    ->withMeta([
        'server_time' => now()->toIso8601String(),
        'api_version' => 'v1.4',
        'cache_hit'   => true,
    ])
    ->success('Dashboard metrics', $metrics);
```

**JSON Output:**
```json
{
  "ok": true,
  "message": "Dashboard metrics",
  "data": { ... },
  "meta": {
    "server_time": "2026-11-12T15:30:00+06:00",
    "api_version": "v1.4",
    "cache_hit": true
  },
  "trace_id": "req_abcdef123456"
}
```

### Custom Headers & Cookies

```php
return ApiResponse::make()
    ->withHeader('X-RateLimit-Remaining', '95')
    ->withCookie(cookie('session_token', $token, 60))
    ->success('Session renewed', $user);
```

### Force JSON Middleware

Frontend mobile apps sometimes forget to supply the `Accept: application/json` header, causing Laravel to send HTML redirects. Protect your API routes using the bundled middleware:

#### In `bootstrap/app.php` (Laravel 11, 12, 13):
```php
->withMiddleware(function ($middleware) {
    $middleware->api(append: [
        \KaziOmar\ApiResponder\Middleware\ForceJsonResponse::class,
    ]);
})
```

#### Or in `routes/api.php`:
```php
Route::middleware('api.force_json')->group(function () {
    Route::apiResource('users', UserController::class);
});
```

---

## 📋 Comprehensive Method & Status Code Matrix

| Helper Function | Controller Trait (`HasApiResponse`) | Facade (`ApiResponse::`) | HTTP Status Code | Typical Use Case |
| :--- | :--- | :--- | :--- | :--- |
| `response_success($msg, $data)` | `$this->success($msg, $data)` | `ApiResponse::success(...)` | `200 OK` | Fetching resources, general success |
| `response_created($data, $msg)` | `$this->created($data, $msg)` | `ApiResponse::created(...)` | `201 Created` | Storing a new record |
| `response_accepted($data, $msg)` | `$this->accepted($data, $msg)` | `ApiResponse::accepted(...)` | `202 Accepted` | Async jobs queued for processing |
| `response_no_content()` | `$this->noContent()` | `ApiResponse::noContent()` | `204 No Content` | Record successfully deleted |
| `response_bad_request($msg, $err)` | `$this->badRequest($msg, $err)` | `ApiResponse::badRequest(...)` | `400 Bad Request` | Business rule failure, bad input |
| `response_unauthorized($msg, $err)`| `$this->unauthorized($msg, $err)` | `ApiResponse::unauthorized(...)`| `401 Unauthorized` | Invalid/missing authentication token |
| `response_forbidden($msg, $err)` | `$this->forbidden($msg, $err)` | `ApiResponse::forbidden(...)` | `403 Forbidden` | User authenticated but unauthorized |
| `response_not_found($msg, $err)` | `$this->notFound($msg, $err)` | `ApiResponse::notFound(...)` | `404 Not Found` | Model or route record not found |
| `response_validation_error($val)` | `$this->validationError($val)` | `ApiResponse::validationError(...)` | `422 Unprocessable` | Form input validation failure |
| `response_server_error($msg, $e)` | `$this->serverError($msg, $e)` | `ApiResponse::serverError(...)` | `500 Server Error` | Database/code uncaught crash |
| `response_error($msg, $err, $code)`| `$this->error($msg, $err, $code)`| `ApiResponse::error(...)` | Custom (`4xx` / `5xx`) | Flexible error responses |

---

## ⚙️ Configuration Reference (`config/api-response.php`)

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Standard Response Keys
    |--------------------------------------------------------------------------
    | Rename keys to match your frontend conventions (e.g. 'ok' => 'success').
    */
    'keys' => [
        'status'     => 'ok',          // boolean flag (true/false)
        'message'    => 'message',     // human-readable summary
        'data'       => 'data',        // payload container for success
        'errors'     => 'errors',      // error details container
        'pagination' => 'pagination',  // pagination metadata container
        'meta'       => 'meta',        // additional custom metadata
        'trace_id'   => 'trace_id',    // unique request tracking id
    ],

    /*
    |--------------------------------------------------------------------------
    | Request / Trace ID Tracking
    |--------------------------------------------------------------------------
    */
    'trace_id' => [
        'enabled'       => true,
        'header_name'   => 'X-Request-Id',
        'only_on_error' => false,      // if true, only appears in error responses
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Metadata Keys
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'total'             => 'total',
        'count'             => 'count',
        'per_page'          => 'per_page',
        'current_page'      => 'current_page',
        'total_pages'       => 'total_pages',
        'has_more'          => 'has_more',
        'next_cursor'       => 'next_cursor',
        'prev_cursor'       => 'prev_cursor',
        'include_when_null' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Exception Handling
    |--------------------------------------------------------------------------
    */
    'exceptions' => [
        'auto_handle'       => true,   // Auto-catches API exceptions out of the box
        'only_api_requests' => true,   // Skips non-API web routes
        'log_unhandled'     => true,
    ],

    'include_null_data'   => false,
    'include_null_errors' => true,
    'debug_exceptions'    => env('APP_DEBUG', false),

    'headers' => [
        'Content-Type' => 'application/json',
    ],

    'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
];
```

---

## 🛠️ Local Package Development Setup

If you are developing this package locally within a Laravel project:

1. Clone or copy the package into:
   ```
   packages/KaziOmar/laravel-api-responder
   ```
2. In your Laravel project's `composer.json`, register the local path repository:
   ```json
   "repositories": [
       {
           "type": "path",
           "url": "packages/KaziOmar/laravel-api-responder"
       }
   ],
   "require": {
       "thekaziomar/laravel-api-responder": "@dev"
   }
   ```
3. Run `composer update thekaziomar/laravel-api-responder`.

---

## 🧪 Testing

The package includes a comprehensive PHPUnit suite covering every envelope type, pagination extractor, error normalizer, and HTTP pipeline exception scenario.

Run the test suite:

```bash
composer test
```

Or via PHPUnit directly:

```bash
./vendor/bin/phpunit
```

```
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.12
Configuration: phpunit.xml.dist

...........................                                       27 / 27 (100%)

Time: 00:00.182, Memory: 30.00 MB

OK (27 tests, 112 assertions)
```

---

## 📄 License & Credits

The MIT License (MIT). Please see [License File](LICENSE) for more details.

Crafted with ❤️ by **[Kazi Omar Faruk](https://github.com/kaziomarsh24)**.
