<?php

return [
    'endpoint' => env('AI_SERVICE_URL', 'http://localhost:8001'),
    'api_key'  => env('AI_SERVICE_KEY', "test_null"),
    'connect_timeout' => (int) env('AI_SERVICE_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('AI_SERVICE_TIMEOUT', 180),
    'ingest_timeout' => (int) env('AI_SERVICE_INGEST_TIMEOUT', 300),
];
