<?php
/**
 * API результатів гри
 *
 * Ендпоінти:
 *   GET ?action=scoreboard — рахунок по командах
 *   GET ?action=detailed   — детальна таблиця всіх відповідей
 *
 * @package QuestGame\API
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/game.php';

requireAuth();

$action = $_GET['action'] ?? '';
$db     = Database::getConnection();

try {
    switch ($action) {

        // ============================================================
        // РАХУНОК ПО КОМАНДАХ
        // ============================================================
        case 'scoreboard':
            $scores = getTeamScores($db);
            jsonResponse(200, true, 'OK', ['scores' => $scores]);
            break;

        // ============================================================
        // ДЕТАЛЬНА ТАБЛИЦЯ ВІДПОВІДЕЙ
        // ============================================================
        case 'detailed':
            $stmt = $db->query(
                'SELECT a.id,
                        u.username,
                        u.team,
                        q.question_text,
                        a.selected_option,
                        a.is_correct,
                        a.points_earned,
                        a.answered_at,
                        q.options,
                        q.correct_answer
                 FROM answers a
                 JOIN users u ON a.user_id = u.id
                 JOIN questions q ON a.question_id = q.id
                 ORDER BY a.answered_at DESC'
            );

            $answers = $stmt->fetchAll();

            // Додаємо текстові значення обраної та правильної відповідей
            foreach ($answers as &$row) {
                $opts = json_decode($row['options'], true);
                $row['selected_text'] = $opts[(int)$row['selected_option']] ?? '—';
                $row['correct_text']  = $opts[(int)$row['correct_answer']]  ?? '—';
                // Видаляємо сирий JSON — він не потрібен клієнту
                unset($row['options']);
            }
            unset($row);

            jsonResponse(200, true, 'OK', ['answers' => $answers]);
            break;

        default:
            jsonResponse(400, false, 'Невідома дія');
    }
} catch (Exception $e) {
    error_log('Results API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}