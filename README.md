# Laravel API Responder

[![Run Tests](https://github.com/kaziomarsh24/laravel-api-responder/actions/workflows/run-tests.yml/badge.svg)](https://github.com/kaziomarsh24/laravel-api-responder/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/thekaziomar/laravel-api-responder.svg?style=flat-square)](https://packagist.org/packages/thekaziomar/laravel-api-responder)
[![Total Downloads](https://img.shields.io/packagist/dt/thekaziomar/laravel-api-responder.svg?style=flat-square)](https://packagist.org/packages/thekaziomar/laravel-api-responder)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20to%208.5+-777BB4.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-ff2d20.svg?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/github/license/kaziomarsh24/laravel-api-responder.svg?style=flat-square)](LICENSE)

> **Lightweight, production-grade, highly-reusable Laravel package for standardized JSON API responses with smart automatic pagination and resource extraction.**

Eliminate repetitive `response()->json(...)` boilerplate across your controllers, achieve consistent JSON envelopes for your frontend clients (React, Vue, Flutter, iOS, Android), and enjoy automatic pagination separation without writing metadata extraction loops.

---

## 🚀 Key Highlights

- **Standardized Response Envelope**: Predictable `{ "ok": true, "message": "...", "data": ... }` for success and `{ "ok": false, "message": "...", "errors": ... }` for errors.
- **Smart Automatic Pagination Extraction**:
  - Automatically detects `LengthAwarePaginator`, `CursorPaginator`, and simple `Paginator`.
  - Seamlessly unpacks `JsonResource` and `ResourceCollection` (e.g. `UserResource::collection($users)`), resolving transformed items into `data` while isolating pagination metadata into a root-level `pagination` object.
- **3 Ergonomic Ways to Use**:
  1. **Global Helper Functions** (`response_success()`, `response_error()`, `response_created()`, `response_not_found()`, etc.)
  2. **Controller Trait** (`HasApiResponse`)
  3. **Facade** (`ApiResponse::success()`, `ApiResponse::error()`)
- **Robust Error Normalization & Type Safety**:
  - Pass a `Validator`, `ValidationException`, `MessageBag`, `Throwable`/`Exception`, `string`, or `array` to `response_error()` without ever triggering a PHP type error.
- **Fluent Chaining**:
  - Attach custom metadata (`withMeta()`), custom HTTP headers (`withHeader()`), and cookies (`withCookie()`).
- **Fully Configurable**:
  - Customize envelope keys (`ok` ➔ `success`, `data` ➔ `payload`), pagination keys, default status messages, and JSON options via `config/api-response.php`.

---

## 📦 Installation

Install the package via Composer:

```bash
composer require kaziomar/laravel-api-responder
```

The service provider and `ApiResponse` facade will automatically be registered via Laravel Package Auto-Discovery.

### Publish Configuration (Optional)

Publish the configuration file if you want to customize response keys or defaults:

```bash
php artisan vendor:publish --tag="api-responder-config"
```

This creates `config/api-response.php`.

---

## ⚡ Quickstart

### 1. Using Global Helper Functions

```php
use App\Models\User;
use App\Http\Resources\UserResource;

// Simple success
return response_success('Profile retrieved', $user);

// Ergonomic swap: pass data directly as first parameter!
return response_success($user);

// 201 Created
return response_created($user, 'User registered successfully');

// Paginated collections (automatically extracts pagination metadata!)
return response_success('Users fetched', UserResource::collection(User::paginate(20)));

// Error responses
return response_error('Invalid email or password', null, 401);

// Semantic error shortcuts
return response_not_found('User not found');
return response_unauthorized('Please log in first');
return response_forbidden('You lack permissions for this action');
return response_validation_error($validator);
```

### 2. Using the Controller Trait

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use KaziOmar\ApiResponder\Traits\HasApiResponse;

class PostController extends Controller
{
    use HasApiResponse;

    public function index()
    {
        $posts = Post::latest()->paginate(15);
        return $this->success('Posts fetched', $posts);
    }

    public function store(Request $request)
    {
        $post = Post::create($request->validated());
        return $this->created($post, 'Post created');
    }

    public function show($id)
    {
        $post = Post::find($id);

        if (! $post) {
            return $this->notFound('Post not found');
        }

        return $this->success('Post found', $post);
    }
}
```

### 3. Using the Facade

```php
use KaziOmar\ApiResponder\Facades\ApiResponse;

return ApiResponse::success('Data updated', $payload);
return ApiResponse::error('Action rejected', ['reason' => 'Account suspended'], 403);
```

---

## 📐 JSON Response Envelopes

### 1. Standard Success Response (`200 OK`)

```json
{
  "ok": true,
  "message": "User profile retrieved",
  "data": {
    "id": 1,
    "name": "Kazi Omar Faruk",
    "email": "softeng.kaziomar@gmail.com"
  }
}
```

### 2. Smart Paginated Response (`LengthAwarePaginator`)

When passing any paginator or a `ResourceCollection` wrapping a paginator (e.g. `UserResource::collection(User::paginate(20))`):

```json
{
  "ok": true,
  "message": "Users fetched",
  "data": [
    { "id": 1, "name": "Alice" },
    { "id": 2, "name": "Bob" }
  ],
  "pagination": {
    "total": 100,
    "count": 20,
    "per_page": 20,
    "current_page": 1,
    "total_pages": 5,
    "has_more": true
  }
}
```

### 3. Cursor Paginated Response (`CursorPaginator`)

```json
{
  "ok": true,
  "message": "Stream fetched",
  "data": [ ... ],
  "pagination": {
    "count": 25,
    "per_page": 25,
    "has_more": true,
    "next_cursor": "eyJpZCI6MjV9",
    "prev_cursor": null
  }
}
```

### 4. Standard Error Response (`400 Bad Request`)

```json
{
  "ok": false,
  "message": "Something went wrong",
  "errors": {
    "code": "ERR_PAYMENT_FAILED"
  }
}
```

### 5. Validation Error Response (`422 Unprocessable Entity`)

Pass your `Validator` instance or `ValidationException` directly:

```php
$validator = Validator::make($request->all(), [
    'email' => 'required|email',
    'password' => 'required|min:8',
]);

if ($validator->fails()) {
    return response_validation_error($validator);
}
```

Produces:

```json
{
  "ok": false,
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "password": [
      "The password field must be at least 8 characters."
    ]
  }
}
```

---

## 🛠 Fluent Chaining & Metadata

Add custom headers, cookies, or top-level `meta` fields effortlessly:

```php
use KaziOmar\ApiResponder\Facades\ApiResponse;

return ApiResponse::make()
    ->withHeader('X-RateLimit-Remaining', '99')
    ->withMeta([
        'server_time' => now()->toIso8601String(),
        'api_version' => 'v1.2',
    ])
    ->withCookie(cookie('session_id', $token, 60))
    ->success('Dashboard metrics loaded', $metrics);
```

Produces:

```json
{
  "ok": true,
  "message": "Dashboard metrics loaded",
  "data": { ... },
  "meta": {
    "server_time": "2026-11-12T14:43:00+00:00",
    "api_version": "v1.2"
  }
}
```

---

## 📋 Comprehensive API Method Reference

| Global Helper | Controller Trait (`HasApiResponse`) | Facade (`ApiResponse::`) | HTTP Status |
| :--- | :--- | :--- | :--- |
| `response_success($msg, $data, $code)` | `$this->success($msg, $data, $code)` | `ApiResponse::success(...)` | `200` |
| `response_created($data, $msg)` | `$this->created($data, $msg)` | `ApiResponse::created(...)` | `201` |
| `response_accepted($data, $msg)` | `$this->accepted($data, $msg)` | `ApiResponse::accepted(...)` | `202` |
| `response_no_content()` | `$this->noContent()` | `ApiResponse::noContent()` | `204` |
| `response_bad_request($msg, $errors)` | `$this->badRequest($msg, $errors)` | `ApiResponse::badRequest(...)` | `400` |
| `response_unauthorized($msg, $errors)`| `$this->unauthorized($msg, $errors)` | `ApiResponse::unauthorized(...)`| `401` |
| `response_forbidden($msg, $errors)` | `$this->forbidden($msg, $errors)` | `ApiResponse::forbidden(...)` | `403` |
| `response_not_found($msg, $errors)` | `$this->notFound($msg, $errors)` | `ApiResponse::notFound(...)` | `404` |
| `response_validation_error($val, $msg)`| `$this->validationError($val, $msg)`| `ApiResponse::validationError(...)`| `422` |
| `response_server_error($msg, $err)` | `$this->serverError($msg, $err)` | `ApiResponse::serverError(...)` | `500` |
| `response_error($msg, $errors, $code)`| `$this->error($msg, $errors, $code)`| `ApiResponse::error(...)` | Dynamic |

---

## ⚙️ Configuration Reference

In `config/api-response.php`:

```php
return [
    // Top-level envelope keys
    'keys' => [
        'status'     => 'ok',          // change to 'success' if preferred
        'message'    => 'message',
        'data'       => 'data',        // change to 'payload' or 'result'
        'errors'     => 'errors',
        'pagination' => 'pagination',
        'meta'       => 'meta',
    ],

    // Pagination inner keys
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

    // Fallback messages
    'messages' => [
        'success'          => 'Success',
        'created'          => 'Resource created successfully',
        'accepted'         => 'Request accepted for processing',
        'no_content'       => '',
        'bad_request'      => 'Bad request',
        'unauthorized'     => 'Unauthorized access',
        'forbidden'        => 'Access forbidden',
        'not_found'        => 'Resource not found',
        'validation_error' => 'Validation failed',
        'server_error'     => 'An internal server error occurred',
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

## 🧪 Testing

Run test suite via PHPUnit:

```bash
composer test
```

Or directly:

```bash
./vendor/bin/phpunit
```

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

Developed with ❤️ by **[Kazi Omar Faruk](https://github.com/kaziomarsh24)**.
