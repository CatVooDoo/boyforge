<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
env_load();

// Валидация критических секретов
env_get('CLOUDPAYMENTS_API_SECRET', true);
env_get('CLOUDPAYMENTS_PUBLIC_ID', true);
env_get('5POST_API_KEY', true);
env_get('GOOGLE_SCRIPT_URL', true);

// База данных
env_get('DB_HOST', true);
env_get('DB_PORT', true);
env_get('DB_DATABASE', true);
env_get('DB_USERNAME', true);
env_get('DB_PASSWORD', true);
