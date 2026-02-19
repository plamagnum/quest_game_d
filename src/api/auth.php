<?php
/**
 * API авторизації та реєстрації
 *
 * Ендпоінти:
 *   POST ?action=register — реєстрація нового користувача
 *   POST ?action=login    — авторизація
 *   GET  ?action=logout   — вихід із системи
 *   GET  ?action=check    — перевірка поточної сесії
 *
 * @package QuestGame\API
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

$action = $_GET['action'] ?? '';
$db     = Database::getConnection();

try {
    switch ($action) {

        // ============================================================
        // РЕЄСТРАЦІЯ
        // ============================================================
        case 'register':
            requirePost();
            $data     = getJsonInput();
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';
            $team     = (int)($data['team'] ?? 1);

            // Валідація
            if ($username === '' || $password === '') {
                jsonResponse(400, false, "Ім'я користувача та пароль обов'язкові");
            }
            if (strlen($username) < 3 || strlen($username) > 50) {
                jsonResponse(400, false, "Ім'я має бути від 3 до 50 символів");
            }
            if (strlen($password) < 4) {
                jsonResponse(400, false, 'Пароль має бути мінімум 4 символи');
            }
            if (!in_array($team, [1, 2], true)) {
                jsonResponse(400, false, 'Команда має бути 1 або 2');
            }

            // Перевірка унікальності імені
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                jsonResponse(409, false, "Користувач з таким ім'ям вже існує");
            }

            // Створення користувача
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                'INSERT INTO users (username, password_hash, team, role) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$username, $hash, $team, 'user']);
            $userId = (int)$db->lastInsertId();

            // Збереження сесії
            $_SESSION['user_id']  = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['team']     = $team;
            $_SESSION['role']     = 'user';

            jsonResponse(201, true, 'Реєстрація успішна', [
                'user_id'  => $userId,
                'username' => $username,
                'team'     => $team,
                'role'     => 'user',
            ]);
            break;

        // ============================================================
        // ВХІД
        // ============================================================
        case 'login':
            requirePost();
            $data     = getJsonInput();
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';

            if ($username === '' || $password === '') {
                jsonResponse(400, false, "Введіть ім'я та пароль");
            }

            // Пошук користувача
            $stmt = $db->prepare(
                'SELECT id, username, password_hash, team, role FROM users WHERE username = ?'
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                jsonResponse(401, false, "Невірне ім'я або пароль");
            }

            // Збереження сесії
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['team']     = (int)$user['team'];
            $_SESSION['role']     = $user['role'];

            jsonResponse(200, true, 'Вхід успішний', [
                'user_id'  => (int)$user['id'],
                'username' => $user['username'],
                'team'     => (int)$user['team'],
                'role'     => $user['role'],
            ]);
            break;

        // ============================================================
        // ВИХІД
        // ============================================================
        case 'logout':
            session_destroy();
            jsonResponse(200, true, 'Ви вийшли з системи');
            break;

        // ============================================================
        // ПЕРЕВІРКА СЕСІЇ
        // ============================================================
        case 'check':
            if (isset($_SESSION['user_id'])) {
                jsonResponse(200, true, 'Авторизовано', [
                    'user_id'  => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'team'     => $_SESSION['team'],
                    'role'     => $_SESSION['role'],
                ]);
            }
            jsonResponse(401, false, 'Не авторизовано');
            break;

        default:
            jsonResponse(400, false, 'Невідома дія');
    }
} catch (Exception $e) {
    error_log('Auth API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}