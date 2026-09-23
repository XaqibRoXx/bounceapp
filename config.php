<?php
declare(strict_types=1);

function bounce_load_env(string $path): array
{
    if (!is_file($path)) return [];
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) $value = substr($value, 1, -1);
        $vars[$key] = $value;
    }
    return $vars;
}

$env = bounce_load_env(__DIR__ . '/.env');
return [
    'app_name' => $env['APP_NAME'] ?? 'BounceApp',
    'app_url' => rtrim($env['APP_URL'] ?? '', '/'),
    'app_env' => $env['APP_ENV'] ?? 'production',
    'app_debug' => filter_var($env['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'timezone' => $env['APP_TIMEZONE'] ?? 'Asia/Karachi',
    'db' => [
        'host' => $env['DB_HOST'] ?? 'localhost',
        'port' => $env['DB_PORT'] ?? '3306',
        'name' => $env['DB_DATABASE'] ?? '',
        'user' => $env['DB_USERNAME'] ?? '',
        'pass' => $env['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
    ],
];
