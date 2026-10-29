<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper. The application targets MySQL (production). A SQLite
 * adapter exists so the same code can run in a self-contained preview
 * environment; SQL is therefore kept inside the ANSI subset that both
 * engines understand.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static array $config = [];
    private static int $txLevel = 0;
    private static array $queries = [];

    public static function boot(array $config): void
    {
        self::$config = $config;
    }

    public static function config(): array
    {
        return self::$config;
    }

    public static function driver(): string
    {
        return self::$config['db_driver'] ?? 'mysql';
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if (self::isSqlite()) {
                $path = self::$config['db_sqlite_path'];
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA journal_mode = WAL');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    self::$config['db_host'],
                    self::$config['db_port'],
                    self::$config['db_database'],
                    self::$config['db_charset'] ?? 'utf8mb4'
                );
                self::$pdo = new PDO($dsn, self::$config['db_username'], self::$config['db_password'], $options);
                if (self::isMysql()) {
                    self::$pdo->exec("SET time_zone = '+07:00'");
                }
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Koneksi database gagal: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return self::$pdo;
    }

    private static function isMysql(): bool
    {
        return self::driver() === 'mysql';
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $started = microtime(true);
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        self::$queries[] = ['sql' => $sql, 'ms' => round((microtime(true) - $started) * 1000, 2)];
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn ($c) => ':' . $c, $columns))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(static fn ($c) => $c . ' = ?', array_keys($data)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, $set, $where);
        $params = array_merge(array_values($data), array_values($whereParams));
        return self::run($sql, $params)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run(sprintf('DELETE FROM %s WHERE %s', $table, $where), $params)->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        self::pdo()->beginTransaction();
        try {
            $result = $callback();
            self::pdo()->commit();
            return $result;
        } catch (\Throwable $e) {
            if (self::pdo()->inTransaction()) {
                self::pdo()->rollBack();
            }
            throw $e;
        }
    }

    public static function tableExists(string $table): bool
    {
        try {
            if (self::isSqlite()) {
                $found = self::value(
                    "SELECT name FROM sqlite_master WHERE type IN ('table','view') AND name = ?",
                    [$table]
                );
            } else {
                $found = self::value(
                    'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                    [self::$config['db_database'], $table]
                );
            }
            return $found !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function queries(): array
    {
        return self::$queries;
    }

    /** Escape a LIKE pattern fragment. */
    public static function like(string $value): string
    {
        return '%' . str_replace(['%', '_'], ['\%', '\_'], $value) . '%';
    }
}
