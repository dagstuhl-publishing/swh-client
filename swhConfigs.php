<?php

$envFile = __DIR__ . '/.env';

if (file_exists($envFile)) {
    $envVariables = parse_ini_file($envFile);
    foreach ($envVariables as $key => $value) {
        putenv("$key=$value");
    }
}

return [
    'production' => [
        'token' => getenv('SWH_TOKEN_PROD'),
        'api-url' => getenv('SWH_API_URL_PROD'),
    ],
    'staging' => [
        'token' => getenv('SWH_TOKEN_STAGING'),
        'api-url' => getenv('SWH_API_URL_STAGING')
    ]

];
