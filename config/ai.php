<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI service
    |--------------------------------------------------------------------------
    |
    | The Python service that reads handwriting and proposes marks. It holds the
    | model credentials, never Laravel, so a tenant database dump cannot leak a
    | provider key and a browser can never call a model directly (AIG-10).
    |
    | AI_INTERNAL_KEY is a shared secret this backend sends as X-Internal-Key.
    | Leaving it empty disables the check on the Python side, which is a
    | developer-machine state only. Set it before any shared deployment, and
    | check /api/v1/ai/health which reports whether a key is required.
    |
    */

    'service_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8100'),

    'internal_key' => env('AI_INTERNAL_KEY', ''),

    /*
    | Reading a page takes 13 seconds in measurement and grading a script takes
    | 10 to 25, so the request budget is generous. It still has to be a bound,
    | because an unbounded call against a slow model ties up a queue worker.
    */
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 180),
];
