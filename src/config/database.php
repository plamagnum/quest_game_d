<?php
/**
 * PDO Singleton підключення до MySQL
 *
 * Використовує environment variables з Docker.
 * Єдиний екземпляр з'єднання на весь запит.
 *
 * @package QuestGame\Config
 */

declare(strict_types=1);

class Database
{
    /** @var PDO|null Єдиний екземпляр PDO */
    private static ?PDO $instance = null;

    /**
     * Отримати з'єднання з БД
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: 'db',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_DATABASE') ?: 'quest_db'
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    getenv('DB_USERNAME') ?: 'quest_user',
                    getenv('DB_PASSWORD') ?: 'quest_secret_pass',
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                error_log('DB Connection Error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Помилка підключення до бази даних'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        return self::$instance;
    }

    /** Заборона клонування */
    private function __clone() {}

    /** Заборона десеріалізації */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}