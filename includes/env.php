<?php
declare(strict_types=1);

function env_load(): void {
    static $loaded = false;
    if ($loaded) return;
    
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) {
        die('CRITICAL: .env file not found');
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        die('CRITICAL: Failed to read .env file');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        
        $GLOBALS['__ENV'][$key] = $value;
    }
    $loaded = true;
}

function env_get(string $key, bool $required = false) {
    $value = $GLOBALS['__ENV'][$key] ?? null;
    
    if ($required && ($value === null || $value === '')) {
        die("CRITICAL: Missing required environment variable: {$key}");
    }
    
    return $value;
}
