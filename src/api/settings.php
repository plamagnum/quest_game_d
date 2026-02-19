<?php
/**
 * API налаштувань гри
 *
 * Ендпоінти:
 *   GET  ?action=get          — отримати всі налаштування (всім авторизованим)
 *   POST ?action=update_teams — оновити назви команд (тільки адмін)
 *
 * @package QuestGame\API
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAuth();

$action = $_GET['action'] ?? '';
$db     = Database::getConnection();

try {
    switch ($action) {

        // ============================================================
        // ОТРИМАТИ НАЛАШТУВАННЯ
        // ============================================================
        case 'get':
            $stmt = $db->query('SELECT `key`, `value` FROM settings');
            $rows = $stmt->fetchAll();

            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['key']] = $row['value'];
            }

            // Гарантуємо що назви команд завжди є
            if (!isset($settings['team1_name'])) $settings['team1_name'] = 'Команда 1';
            if (!isset($settings['team2_name'])) $settings['team2_name'] = 'Команда 2';

            jsonResponse(200, true, 'OK', ['settings' => $settings]);
            break;

        // ============================================================
        // ОНОВИТИ НАЗВИ КОМАНД (тільки адмін)
        // ============================================================
        case 'update_teams':
            requireAdminRole();
            requirePost();

            $data  = getJsonInput();
            $team1 = trim($data['team1_name'] ?? '');
            $team2 = trim($data['team2_name'] ?? '');

            if ($team1 === '' || $team2 === '') {
                jsonResponse(400, false, 'Назви команд не можуть бути порожніми');
            }
            if (mb_strlen($team1) > 50 || mb_strlen($team2) > 50) {
                jsonResponse(400, false, 'Назва команди — максимум 50 символів');
            }

            // Upsert для team1_name
            $stmt = $db->prepare(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            $stmt->execute(['team1_name', $team1]);
            $stmt->execute(['team2_name', $team2]);

            jsonResponse(200, true, 'Назви команд оновлено', [
                'team1_name' => $team1,
                'team2_name' => $team2,
            ]);
            break;

        default:
            jsonResponse(400, false, 'Невідома дія');
    }
} catch (Exception $e) {
    error_log('Settings API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}