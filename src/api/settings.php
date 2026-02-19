<?php
/**
 * API налаштувань гри
 *
 * Ендпоінти:
 *   GET  ?action=get              — отримати всі налаштування (всім авторизованим)
 *   POST ?action=update_teams     — оновити назви команд (тільки адмін)
 *   POST ?action=adjust_score     — додати/відняти бали команді (тільки адмін)
 *   GET  ?action=adjustments      — історія коригувань (тільки адмін)
 *   POST ?action=delete_adjust    — видалити коригування (тільки адмін)
 *   POST ?action=clear_adjustments — видалити всі коригування (тільки адмін)
 *   POST ?action=toggle_options   — перемкнути показ варіантів відповідей (тільки адмін)
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

        // ============================================================
        // ДОДАТИ/ВІДНЯТИ БАЛИ (тільки адмін)
        // ============================================================
        case 'adjust_score':
            requireAdminRole();
            requirePost();

            $data   = getJsonInput();
            $team   = (int)($data['team'] ?? 0);
            $points = (int)($data['points'] ?? 0);
            $reason = trim($data['reason'] ?? '');

            if ($team !== 1 && $team !== 2) {
                jsonResponse(400, false, 'Вкажіть команду: 1 або 2');
            }
            if ($points === 0) {
                jsonResponse(400, false, 'Бали не можуть бути нулем');
            }
            if (abs($points) > 1000) {
                jsonResponse(400, false, 'Максимум ±1000 балів за раз');
            }
            if (mb_strlen($reason) > 255) {
                jsonResponse(400, false, 'Причина — максимум 255 символів');
            }

            $stmt = $db->prepare(
                'INSERT INTO score_adjustments (team, points, reason, admin_id) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$team, $points, $reason, $_SESSION['user_id']]);

            jsonResponse(200, true, 'Бали скориговано', [
                'team'   => $team,
                'points' => $points,
            ]);
            break;

        // ============================================================
        // ІСТОРІЯ КОРИГУВАНЬ (тільки адмін)
        // ============================================================
        case 'adjustments':
            requireAdminRole();

            $stmt = $db->query(
                'SELECT sa.id, sa.team, sa.points, sa.reason, sa.created_at,
                        u.username AS admin_username
                 FROM score_adjustments sa
                 JOIN users u ON u.id = sa.admin_id
                 ORDER BY sa.created_at DESC'
            );
            $adjustments = $stmt->fetchAll();

            foreach ($adjustments as &$row) {
                $row['id']     = (int)$row['id'];
                $row['team']   = (int)$row['team'];
                $row['points'] = (int)$row['points'];
            }
            unset($row);

            jsonResponse(200, true, 'OK', ['adjustments' => $adjustments]);
            break;

        // ============================================================
        // ВИДАЛИТИ ОДНЕ КОРИГУВАННЯ (тільки адмін)
        // ============================================================
        case 'delete_adjust':
            requireAdminRole();
            requirePost();

            $data = getJsonInput();
            $id   = (int)($data['id'] ?? 0);

            if ($id <= 0) {
                jsonResponse(400, false, 'Невірний ID коригування');
            }

            $stmt = $db->prepare('DELETE FROM score_adjustments WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(404, false, 'Коригування не знайдено');
            }

            jsonResponse(200, true, 'Коригування видалено');
            break;

        // ============================================================
        // ВИДАЛИТИ ВСІ КОРИГУВАННЯ (тільки адмін)
        // ============================================================
        case 'clear_adjustments':
            requireAdminRole();
            requirePost();

            $db->exec('DELETE FROM score_adjustments');

            jsonResponse(200, true, 'Всі коригування видалено');
            break;

        // ============================================================
        // ПЕРЕМКНУТИ ПОКАЗ ВАРІАНТІВ ВІДПОВІДЕЙ (тільки адмін)
        // ============================================================
        case 'toggle_options':
            requireAdminRole();
            requirePost();

            $data        = getJsonInput();
            $showOptions = isset($data['show_options']) ? (bool)$data['show_options'] : true;
            $value       = $showOptions ? '1' : '0';

            $stmt = $db->prepare(
                'INSERT INTO settings (`key`, `value`) VALUES (\'show_options\', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            $stmt->execute([$value]);

            jsonResponse(200, true, 'Налаштування оновлено', ['show_options' => $showOptions]);
            break;
    }
} catch (Exception $e) {
    error_log('Settings API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}