<?php

declare(strict_types=1);

/**
 * Conexão PDO singleton com MySQL.
 * Aceita .env local e variáveis de ambiente (Railway / Docker).
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static bool $bootstrapped = false;

    public static function enabled(): bool
    {
        self::bootstrapEnv();
        $flag = self::env('DB_ENABLED', 'false');
        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::bootstrapEnv();
        $cfg = self::connectionConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'],
            $cfg['name']
        );

        self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    /** @return array{host:string,port:string,name:string,user:string,pass:string} */
    public static function connectionConfig(): array
    {
        self::bootstrapEnv();

        // URL única (Railway MySQL: MYSQL_URL / DATABASE_URL)
        foreach (['MYSQL_URL', 'DATABASE_URL', 'DB_URL'] as $urlKey) {
            $url = self::env($urlKey);
            if ($url && str_starts_with($url, 'mysql')) {
                $parts = parse_url($url);
                if (is_array($parts) && !empty($parts['host'])) {
                    return [
                        'host' => (string) $parts['host'],
                        'port' => (string) ($parts['port'] ?? '3306'),
                        'name' => ltrim((string) ($parts['path'] ?? '/railway'), '/'),
                        'user' => (string) ($parts['user'] ?? 'root'),
                        'pass' => (string) ($parts['pass'] ?? ''),
                    ];
                }
            }
        }

        return [
            // DB_* explícito, depois variáveis padrão do plugin MySQL do Railway
            'host' => self::env('DB_HOST') ?: self::env('MYSQLHOST') ?: '127.0.0.1',
            'port' => self::env('DB_PORT') ?: self::env('MYSQLPORT') ?: '3306',
            'name' => self::env('DB_NAME') ?: self::env('MYSQLDATABASE') ?: 'sorteador_convenios',
            'user' => self::env('DB_USER') ?: self::env('MYSQLUSER') ?: 'root',
            'pass' => self::env('DB_PASS') ?? self::env('MYSQLPASSWORD') ?? '',
        ];
    }

    public static function env(string $key, ?string $default = null): ?string
    {
        self::bootstrapEnv();
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $value = (string) $value;
            if ($value !== '') {
                return $value;
            }
            // senha vazia é válida
            if ($key === 'DB_PASS' || $key === 'MYSQLPASSWORD') {
                return '';
            }
        }
        return $default;
    }

    private static function bootstrapEnv(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $envFile = dirname(__DIR__) . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Variáveis já definidas no ambiente (Railway) têm prioridade sobre o .env
            $already = getenv($key);
            if ($already !== false && $already !== '') {
                $_ENV[$key] = $already;
                continue;
            }
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
