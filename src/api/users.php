<?php
/**
 * API управління гравцями
 *
 * Ендпоінти (тільки для адміна):
 *   GET  ?action=list    — список всіх гравців
 *   POST ?action=block   — заблокувати гравця
 *   POST ?action=unblock — розблокувати гравця
 *   POST ?action=delete  — видалити гравця
 *
 * @package QuestGame\API
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdminRole();

$action = $_GET['action'] ?? '';
$db     = Database::getConnection();

try {
    switch ($action) {

        // ============================================================
        // СПИСОК ГРАВЦІВ
        // ============================================================
        case 'list':
            $stmt = $db->query(
                'SELECT id, username, team, role, is_blocked, created_at FROM users ORDER BY id ASC'
            );
            $players = $stmt->fetchAll();
            jsonResponse(200, true, 'OK', ['players' => $players]);
            break;

        // ============================================================
        // ЗАБЛОКУВАТИ ГРАВЦЯ
        // ============================================================
        case 'block':
            requirePost();
            $data   = getJsonInput();
            $userId = (int)($data['user_id'] ?? 0);

            if ($userId <= 0) {
                jsonResponse(400, false, 'Невірний user_id');
            }
            if ($userId === (int)$_SESSION['user_id']) {
                jsonResponse(400, false, 'Не можна заблокувати самого себе');
            }

            // Перевірка ролі цільового користувача
            $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $target = $stmt->fetch();
            if (!$target) {
                jsonResponse(404, false, 'Гравця не знайдено');
            }
            if ($target['role'] === 'admin') {
                jsonResponse(400, false, 'Не можна заблокувати адміністратора');
            }

            $stmt = $db->prepare('UPDATE users SET is_blocked = 1 WHERE id = ?');
            $stmt->execute([$userId]);
            jsonResponse(200, true, 'Гравця заблоковано');
            break;

        // ============================================================
        // РОЗБЛОКУВАТИ ГРАВЦЯ
        // ============================================================
        case 'unblock':
            requirePost();
            $data   = getJsonInput();
            $userId = (int)($data['user_id'] ?? 0);

            if ($userId <= 0) {
                jsonResponse(400, false, 'Невірний user_id');
            }

            $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                jsonResponse(404, false, 'Гравця не знайдено');
            }

            $stmt = $db->prepare('UPDATE users SET is_blocked = 0 WHERE id = ?');
            $stmt->execute([$userId]);
            jsonResponse(200, true, 'Гравця розблоковано');
            break;

        // ============================================================
        // ВИДАЛИТИ ГРАВЦЯ
        // ============================================================
        case 'delete':
            requirePost();
            $data   = getJsonInput();
            $userId = (int)($data['user_id'] ?? 0);

            if ($userId <= 0) {
                jsonResponse(400, false, 'Невірний user_id');
            }
            if ($userId === (int)$_SESSION['user_id']) {
                jsonResponse(400, false, 'Не можна видалити самого себе');
            }

            // Перевірка ролі цільового користувача
            $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $target = $stmt->fetch();
            if (!$target) {
                jsonResponse(404, false, 'Гравця не знайдено');
            }
            if ($target['role'] === 'admin') {
                jsonResponse(400, false, 'Не можна видалити адміністратора');
            }

            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            jsonResponse(200, true, 'Гравця видалено');
            break;

        default:
            jsonResponse(400, false, 'Невідома дія');
    }
} catch (Exception $e) {
    error_log('Users API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}
