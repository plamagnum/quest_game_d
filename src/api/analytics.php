<?php
/**
 * API аналітики користувачів
 *
 * Ендпоінти:
 *   POST ?action=collect — зберегти дані клієнта (ОС, браузер, тощо)
 *   GET  ?action=list    — отримати всі дані (тільки адмін)
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
        // ЗБІР ДАНИХ КЛІЄНТА
        //
        // Клієнт відправляє: os, browser, browser_version,
        // screen_resolution, language.
        // Сервер додає: ip_address, user_agent.
        //
        // Зберігається тільки один (останній) запис на користувача.
        // ============================================================
        case 'collect':
            requirePost();
            $data   = getJsonInput();
            $userId = (int)$_SESSION['user_id'];

            $os               = esc($data['os'] ?? '');
            $browser          = esc($data['browser'] ?? '');
            $browserVersion   = esc($data['browser_version'] ?? '');
            $screenResolution = esc($data['screen_resolution'] ?? '');
            $language         = esc($data['language'] ?? '');
            $ipAddress        = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent        = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // Видаляємо старий запис
            $stmt = $db->prepare('DELETE FROM user_analytics WHERE user_id = ?');
            $stmt->execute([$userId]);

            // Вставляємо новий
            $stmt = $db->prepare(
                'INSERT INTO user_analytics
                    (user_id, os, browser, browser_version, screen_resolution, language, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $os,
                $browser,
                $browserVersion,
                $screenResolution,
                $language,
                $ipAddress,
                $userAgent,
            ]);

            jsonResponse(200, true, 'Дані збережено');
            break;

        // ============================================================
        // СПИСОК АНАЛІТИКИ (тільки адмін)
        // ============================================================
        case 'list':
            requireAdminRole();

            $stmt = $db->query(
                'SELECT ua.id,
                        ua.user_id,
                        u.username,
                        u.team,
                        u.role,
                        ua.os,
                        ua.browser,
                        ua.browser_version,
                        ua.screen_resolution,
                        ua.language,
                        ua.ip_address,
                        ua.user_agent,
                        ua.created_at
                 FROM user_analytics ua
                 JOIN users u ON ua.user_id = u.id
                 ORDER BY ua.created_at DESC'
            );

            $analytics = $stmt->fetchAll();

            jsonResponse(200, true, 'OK', ['analytics' => $analytics]);
            break;

        default:
            jsonResponse(400, false, 'Невідома дія');
    }
} catch (Exception $e) {
    error_log('Analytics API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}