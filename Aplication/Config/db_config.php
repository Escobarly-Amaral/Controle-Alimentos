<?php
declare(strict_types=1);

return [
    'driver'   => 'mysql',
    'host'     => getenv('SCA_DB_HOST') ?: '127.0.0.1',
    'port'     => (int) (getenv('SCA_DB_PORT') ?: 3306),
    'dbname'   => getenv('SCA_DB_NAME') ?: 'sca_estoque',
    'user'     => getenv('SCA_DB_USER') ?: 'root',
    'password' => getenv('SCA_DB_PASSWORD') ?: '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];

