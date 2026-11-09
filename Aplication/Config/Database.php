<?php
declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/db_config.php';

            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $config['driver'] ?? 'mysql',
                $config['host'],
                (int) $config['port'],
                $config['dbname'],
                $config['charset'] ?? 'utf8mb4'
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['user'],
                    $config['password'],
                    $config['options'] ?? []
                );
            } catch (PDOException $e) {
                error_log('SCA database connection failed: ' . $e->getMessage());
                throw new RuntimeException('Não foi possível acessar o banco de dados agora.', 0, $e);
            }
        }

        return self::$instance;
    }
}
