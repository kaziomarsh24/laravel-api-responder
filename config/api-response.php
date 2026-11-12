<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standard Response Keys
    |--------------------------------------------------------------------------
    |
    | Define the top-level keys returned in the JSON response envelope.
    | You can customize these keys to match your frontend API conventions
    | (e.g. renaming 'ok' to 'success', or 'data' to 'payload').
    |
    */

    'keys' => [
        'status'     => 'ok',          // boolean flag (true/false)
        'message'    => 'message',     // human-readable summary
        'data'       => 'data',        // payload container for success
        'errors'     => 'errors',      // error details container
        'pagination' => 'pagination',  // pagination metadata container
        'meta'       => 'meta',        // additional custom metadata
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Metadata Keys
    |--------------------------------------------------------------------------
    |
    | Customize the field names inside the root-level pagination object.
    | Supports LengthAwarePaginator, simple Paginator, and CursorPaginator.
    |
    */

    'pagination' => [
        'total'        => 'total',
        'count'        => 'count',
        'per_page'     => 'per_page',
        'current_page' => 'current_page',
        'total_pages'  => 'total_pages',
        'has_more'     => 'has_more',
        'next_cursor'  => 'next_cursor',
        'prev_cursor'  => 'prev_cursor',

        // When false, the 'pagination' key is omitted when data is not paginated.
        'include_when_null' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Response Messages
    |--------------------------------------------------------------------------
    |
    | Fallback messages when no explicit message string is supplied.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Envelope Behavior & Formatting
    |--------------------------------------------------------------------------
    */

    // Whether to include 'data' key when data is null
    'include_null_data' => false,

    // Whether to include 'errors' key when error is null or empty on error responses
    'include_null_errors' => true,

    // When an exception is passed as error, append stack trace & debug details if true
    'debug_exceptions' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Default HTTP Headers
    |--------------------------------------------------------------------------
    */

    'headers' => [
        'Content-Type' => 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Encoding Options
    |--------------------------------------------------------------------------
    */

    'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,

];
