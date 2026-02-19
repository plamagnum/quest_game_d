<?php
/**
 * API управління запитаннями (CRUD)
 *
 * Доступ: тільки адміністратор
 *
 * Ендпоінти:
 *   GET  ?action=list   — отримати список усіх запитань
 *   POST ?action=create — створити нове запитання
 *   POST ?action=update — оновити існуюче запитання
 *   POST ?action=delete — видалити запитання
 *
 * @package QuestGame\API
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

// Перевірка: тільки адмін
requireAdminRole();

$action = $_GET['action'] ?? '';
$db     = Database::getConnection();

try {
    switch ($action) {

        // ============================================================
        // СПИСОК ЗАПИТАНЬ
        // ============================================================
        case 'list':
            $stmt = $db->query(
                'SELECT * FROM questions ORDER BY sort_order ASC, id ASC'
            );
            $questions = $stmt->fetchAll();

            // Декодуємо JSON-поле options для кожного запитання
            foreach ($questions as &$q) {
                $q['options'] = json_decode($q['options'], true);
            }
            unset($q);

            jsonResponse(200, true, 'OK', ['questions' => $questions]);
            break;

        // ============================================================
        // СТВОРЕННЯ ЗАПИТАННЯ
        // ============================================================
        case 'create':
            requirePost();
            $data    = getJsonInput();
            $text    = trim($data['question_text'] ?? '');
            $options = $data['options'] ?? [];
            $correct = (int)($data['correct_answer'] ?? 0);
            $points  = (int)($data['points'] ?? 10);

            // Валідація
            if ($text === '') {
                jsonResponse(400, false, 'Текст запитання обов\'язковий');
            }
            if (count($options) < 2) {
                jsonResponse(400, false, 'Потрібно мінімум 2 варіанти відповідей');
            }
            if ($correct < 0 || $correct >= count($options)) {
                jsonResponse(400, false, 'Невірний індекс правильної відповіді');
            }
            if ($points < 1) {
                jsonResponse(400, false, 'Бали мають бути >= 1');
            }

            // Визначити наступний sort_order
            $maxSort = (int)$db->query(
                'SELECT COALESCE(MAX(sort_order), 0) FROM questions'
            )->fetchColumn();

            $stmt = $db->prepare(
                'INSERT INTO questions (question_text, options, correct_answer, points, is_active, sort_order)
                 VALUES (?, ?, ?, ?, 1, ?)'
            );
            $stmt->execute([
                $text,
                json_encode($options, JSON_UNESCAPED_UNICODE),
                $correct,
                $points,
                $maxSort + 1,
            ]);

            jsonResponse(201, true, 'Запитання створено', [
                'id' => (int)$db->lastInsertId(),
            ]);
            break;

        // ============================================================
        // ОНОВЛЕННЯ ЗАПИТАННЯ
        // ============================================================
        case 'update':
            requirePost();
            $data    = getJsonInput();
            $id      = (int)($data['id'] ?? 0);
            $text    = trim($data['question_text'] ?? '');
            $options = $data['options'] ?? [];
            $correct = (int)($data['correct_answer'] ?? 0);
            $points  = (int)($data['points'] ?? 10);
            $active  = (int)($data['is_active'] ?? 1);

            if ($id <= 0) {
                jsonResponse(400, false, 'ID запитання обов\'язковий');
            }
            if ($text === '') {
                jsonResponse(400, false, 'Текст запитання обов\'язковий');
            }
            if (count($options) < 2) {
                jsonResponse(400, false, 'Потрібно мінімум 2 варіанти');
            }
            if ($correct < 0 || $correct >= count($options)) {
                jsonResponse(400, false, 'Невірний індекс правильної відповіді');
            }

            $stmt = $db->prepare(
                'UPDATE questions
                 SET question_text = ?, options = ?, correct_answer = ?,
                     points = ?, is_active = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $text,
                json_encode($options, JSON_UNESCAPED_UNICODE),
                $correct,
                $points,
                $active,
                $id,
            ]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(404, false, 'Запитання не знайдено');
            }

            jsonResponse(200, true, 'Запитання оновлено');
            break;

        // ============================================================
        // ВИДАЛЕННЯ ЗАПИТАННЯ
        // ============================================================
        case 'delete':
            requirePost();
            $data = getJsonInput();
            $id   = (int)($data['id'] ?? 0);

            if ($id <= 0) {
                jsonResponse(400, false, 'ID запитання обов\'язковий');
            }

            $stmt = $db->prepare('DELETE FROM questions WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(404, false, 'Запитання не знайдено');
            }

            jsonResponse(200, true, 'Запитання видалено');
            break;

        default:
            jsonResponse(400, false, 'Невідома дія');
    }
} catch (Exception $e) {
    error_log('Questions API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}