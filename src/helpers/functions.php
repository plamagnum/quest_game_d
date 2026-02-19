<?php
/**
 * Спільні допоміжні функції для всіх API
 *
 * @package QuestGame\Helpers
 */

declare(strict_types=1);

/**
 * Відправити JSON-відповідь і завершити скрипт
 *
 * @param int    $code    HTTP-код відповіді
 * @param bool   $ok      Успішність запиту
 * @param string $msg     Повідомлення
 * @param array  $data    Додаткові дані
 * @return never
 */
function jsonResponse(int $code, bool $ok, string $msg, array $data = []): never
{
    http_response_code($code);
    $body = ['success' => $ok, 'message' => $msg];
    if (!empty($data)) {
        $body['data'] = $data;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Отримати JSON-дані з тіла запиту
 *
 * @return array Розпарсені дані або порожній масив
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Перевірити що метод — POST, інакше 405
 *
 * @return void
 */
function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, false, 'Метод не дозволений');
    }
}

/**
 * Перевірити що користувач авторизований (є сесія)
 *
 * @return void
 */
function requireAuth(): void
{
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(401, false, 'Не авторизовано');
    }
}

/**
 * Перевірити що користувач — адміністратор
 *
 * @return void
 */
function requireAdminRole(): void
{
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        jsonResponse(403, false, 'Доступ заборонено');
    }
}

/**
 * XSS-безпечне екранування рядка
 *
 * @param string $str Вхідний рядок
 * @return string Екранований рядок
 */
function esc(string $str): string
{
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}