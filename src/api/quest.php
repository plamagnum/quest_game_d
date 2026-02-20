<?php
/**
 * API логіки квесту
 *
 * Ендпоінти:
 *   GET  ?action=state   — стан гри (для polling кожну 1-2 сек)
 *   GET  ?action=current — поточне запитання
 *   POST ?action=buzz    — натиснути кнопку "Я знаю відповідь!"
 *   POST ?action=answer  — дати відповідь на запитання
 *   POST ?action=next    — наступне запитання (адмін)
 *   POST ?action=reset   — скинути блокування кнопки (адмін)
 *   POST ?action=start   — розпочати гру (адмін)
 *   POST ?action=stop    — зупинити гру (адмін)
 *
 * Buzz використовує SELECT ... FOR UPDATE (row-level lock)
 * для запобігання race condition при одночасному натисканні.
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

$action   = $_GET['action'] ?? '';
$db       = Database::getConnection();
$userId   = (int)$_SESSION['user_id'];
$userTeam = (int)$_SESSION['team'];
$userRole = (string)$_SESSION['role'];

try {
    switch ($action) {

        // ============================================================
        // СТАН ГРИ (polling клієнтом кожну 1-2 секунди)
        // ============================================================
        case 'state':
            $state    = getGameState($db);
            $question = null;

            // Якщо є активне запитання — отримати його
            if ($state['current_question_id']) {
                $stmt = $db->prepare(
                    'SELECT id, question_text, options, points FROM questions WHERE id = ?'
                );
                $stmt->execute([$state['current_question_id']]);
                $question = $stmt->fetch();
                if ($question) {
                    $question['options'] = json_decode($question['options'], true);
                }
            }

            // Ім'я того, хто натиснув кнопку
            $lockedByUsername = null;
            if ($state['locked_by_user_id']) {
                $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
                $stmt->execute([$state['locked_by_user_id']]);
                $lockedByUsername = $stmt->fetchColumn() ?: null;
            }

            // Рахунок
                        // Назви команд
            $teamNames = getTeamNames($db);

            echo json_encode([
                'success' => true,
                'data'    => [
                    'is_game_active'     => (bool)$state['is_game_active'],
                    'current_question'   => $question,
                    'is_button_locked'   => (bool)$state['is_button_locked'],
                    'locked_by_team'     => $state['locked_by_team'] ? (int)$state['locked_by_team'] : null,
                    'locked_by_user_id'  => $state['locked_by_user_id'] ? (int)$state['locked_by_user_id'] : null,
                    'locked_by_username' => $lockedByUsername,
                    'my_team'            => $userTeam,
                    'my_user_id'         => $userId,
                    'scores'             => $scores,
                    'team1_name'         => $teamNames['team1_name'],
                    'team2_name'         => $teamNames['team2_name'],
                ],
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // ПОТОЧНЕ ЗАПИТАННЯ
        // ============================================================
        case 'current':
            $state = getGameState($db);

            if (!$state['current_question_id']) {
                jsonResponse(200, true, 'Немає активного запитання', ['question' => null]);
            }

            $stmt = $db->prepare(
                'SELECT id, question_text, options, points FROM questions WHERE id = ?'
            );
            $stmt->execute([$state['current_question_id']]);
            $question = $stmt->fetch();

            if ($question) {
                $question['options'] = json_decode($question['options'], true);
            }

            jsonResponse(200, true, 'OK', ['question' => $question]);
            break;

        // ============================================================
        // BUZZ — натиснути кнопку "Я знаю відповідь!"
        //
        // Використовує транзакцію + FOR UPDATE для атомарності:
        // якщо два гравці натиснуть одночасно, тільки один пройде.
        // ============================================================
        case 'buzz':
            requirePost();

            $db->beginTransaction();
            try {
                // Блокуємо рядок game_state до кінця транзакції
                $stmt = $db->prepare('SELECT * FROM game_state WHERE id = 1 FOR UPDATE');
                $stmt->execute();
                $state = $stmt->fetch();

                // Перевірки
                if (!$state || !$state['is_game_active']) {
                    $db->rollBack();
                    jsonResponse(400, false, 'Гра не активна');
                }
                if (!$state['current_question_id']) {
                    $db->rollBack();
                    jsonResponse(400, false, 'Немає активного запитання');
                }
                if ($state['is_button_locked']) {
                    $db->rollBack();
                    if ((int)$state['locked_by_team'] === $userTeam) {
                        jsonResponse(400, false, 'Вашу команду вже зареєстровано');
                    }
                    jsonResponse(403, false, 'Кнопку вже натиснула інша команда');
                }

                // Блокуємо кнопку для іншої команди
                $stmt = $db->prepare(
                    'UPDATE game_state
                     SET is_button_locked = 1, locked_by_team = ?, locked_by_user_id = ?
                     WHERE id = 1'
                );
                $stmt->execute([$userTeam, $userId]);
                $db->commit();

                jsonResponse(200, true, 'Кнопку натиснуто! Дайте відповідь.', [
                    'locked_by_team'    => $userTeam,
                    'locked_by_user_id' => $userId,
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        // ============================================================
        // ВІДПОВІДЬ — дати відповідь на запитання
        //
        // Тільки той гравець, який натиснув кнопку (locked_by_user_id),
        // може відповісти.
        // ============================================================
        case 'answer':
            requirePost();
            $data           = getJsonInput();
            $selectedOption = (int)($data['selected_option'] ?? -1);
            $state          = getGameState($db);

            // Перевірка: тільки гравець, що натиснув кнопку
            if (!$state['is_button_locked'] || (int)$state['locked_by_user_id'] !== $userId) {
                jsonResponse(403, false, 'Тільки гравець, який натиснув кнопку, може відповідати');
            }

            // Отримати запитання
            $stmt = $db->prepare('SELECT * FROM questions WHERE id = ?');
            $stmt->execute([$state['current_question_id']]);
            $question = $stmt->fetch();

            if (!$question) {
                jsonResponse(404, false, 'Запитання не знайдено');
            }

            // Валідація варіанту
            $options = json_decode($question['options'], true);
            if ($selectedOption < 0 || $selectedOption >= count($options)) {
                jsonResponse(400, false, 'Невірний варіант відповіді');
            }

            // Перевірка: чи вже відповідав
            $stmt = $db->prepare(
                'SELECT id FROM answers WHERE user_id = ? AND question_id = ?'
            );
            $stmt->execute([$userId, $question['id']]);
            if ($stmt->fetch()) {
                jsonResponse(400, false, 'Ви вже відповіли на це запитання');
            }

            // Визначення правильності
            $isCorrect    = ($selectedOption === (int)$question['correct_answer']);
            $pointsEarned = $isCorrect ? (int)$question['points'] : 0;

            // Збереження відповіді
            $stmt = $db->prepare(
                'INSERT INTO answers (user_id, question_id, selected_option, is_correct, points_earned)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $question['id'],
                $selectedOption,
                (int)$isCorrect,
                $pointsEarned,
            ]);

            $message = $isCorrect
                ? "Правильно! +{$pointsEarned} балів"
                : 'Неправильно! 0 балів';

            jsonResponse(200, true, $message, [
                'is_correct'     => $isCorrect,
                'points_earned'  => $pointsEarned,
                'correct_answer' => (int)$question['correct_answer'],
            ]);
            break;

        // ============================================================
        // НАСТУПНЕ ЗАПИТАННЯ (тільки адмін)
        // ============================================================
        case 'next':
            requirePost();
            if ($userRole !== 'admin') {
                jsonResponse(403, false, 'Тільки для адміністратора');
            }

            $state       = getGameState($db);
            $currentSort = 0;

            // Визначити sort_order поточного запитання
            if ($state['current_question_id']) {
                $stmt = $db->prepare('SELECT sort_order FROM questions WHERE id = ?');
                $stmt->execute([$state['current_question_id']]);
                $currentSort = (int)($stmt->fetchColumn() ?: 0);
            }

            // Знайти наступне активне запитання
            $stmt = $db->prepare(
                'SELECT id FROM questions
                 WHERE is_active = 1 AND sort_order > ?
                 ORDER BY sort_order ASC
                 LIMIT 1'
            );
            $stmt->execute([$currentSort]);
            $next = $stmt->fetch();

            if (!$next) {
                // Запитання закінчились — гра завершена
                $db->exec(
                    'UPDATE game_state
                     SET current_question_id = NULL,
                         is_button_locked = 0,
                         locked_by_team = NULL,
                         locked_by_user_id = NULL,
                         is_game_active = 0
                     WHERE id = 1'
                );
                jsonResponse(200, true, 'Запитання закінчились. Гра завершена!', [
                    'game_over' => true,
                ]);
            }

            // Встановити наступне запитання, скинути блокування
            $stmt = $db->prepare(
                'UPDATE game_state
                 SET current_question_id = ?,
                     is_button_locked = 0,
                     locked_by_team = NULL,
                     locked_by_user_id = NULL
                 WHERE id = 1'
            );
            $stmt->execute([$next['id']]);

            jsonResponse(200, true, 'Наступне запитання встановлено', [
                'question_id' => (int)$next['id'],
            ]);
            break;

        // ============================================================
        // СКИНУТИ БЛОКУВАННЯ КНОПКИ (тільки адмін)
        // ============================================================
        case 'reset':
            requirePost();
            if ($userRole !== 'admin') {
                jsonResponse(403, false, 'Тільки для адміністратора');
            }

            $db->exec(
                'UPDATE game_state
                 SET is_button_locked = 0,
                     locked_by_team = NULL,
                     locked_by_user_id = NULL
                 WHERE id = 1'
            );

            jsonResponse(200, true, 'Блокування кнопки скинуто');
            break;

        // ============================================================
        // СТАРТ ГРИ (тільки адмін)
        // ============================================================
        case 'start':
            requirePost();
            if ($userRole !== 'admin') {
                jsonResponse(403, false, 'Тільки для адміністратора');
            }

            // Знайти перше активне запитання
            $stmt = $db->query(
                'SELECT id FROM questions WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1'
            );
            $first = $stmt->fetch();

            if (!$first) {
                jsonResponse(400, false, 'Немає активних запитань для початку гри');
            }

            $stmt = $db->prepare(
                'UPDATE game_state
                 SET current_question_id = ?,
                     is_button_locked = 0,
                     locked_by_team = NULL,
                     locked_by_user_id = NULL,
                     is_game_active = 1
                 WHERE id = 1'
            );
            $stmt->execute([$first['id']]);

            jsonResponse(200, true, 'Гру розпочато!', [
                'question_id' => (int)$first['id'],
            ]);
            break;

        // ============================================================
        // СТОП ГРИ (тільки адмін)
        // ============================================================
        case 'stop':
            requirePost();
            if ($userRole !== 'admin') {
                jsonResponse(403, false, 'Тільки для адміністратора');
            }

            $db->exec(
                'UPDATE game_state
                 SET is_game_active = 0,
                     is_button_locked = 0,
                     locked_by_team = NULL,
                     locked_by_user_id = NULL
                 WHERE id = 1'
            );

            jsonResponse(200, true, 'Гру зупинено');
            break;

        // ============================================================
        // ОБНУЛИТИ РАХУНОК (тільки адмін)
        // ============================================================
        case 'reset_scores':
            requirePost();
            if ($userRole !== 'admin') {
                jsonResponse(403, false, 'Тільки для адміністратора');
            }

            $db->exec('DELETE FROM answers');
            $db->exec(
                'UPDATE game_state
                 SET current_question_id = NULL,
                     is_button_locked = 0,
                     locked_by_team = NULL,
                     locked_by_user_id = NULL,
                     is_game_active = 0
                 WHERE id = 1'
            );
            try {
                $db->exec('DELETE FROM score_adjustments');
            } catch (Exception $e) {
                // Таблиця score_adjustments може не існувати
                error_log('reset_scores: score_adjustments table not found: ' . $e->getMessage());
            }

            jsonResponse(200, true, 'Рахунок обнулено! Гру скинуто.');
            break;

        default:
            jsonResponse(400, false, 'Невідома дія');
    }
} catch (Exception $e) {
    error_log('Quest API Error: ' . $e->getMessage());
    jsonResponse(500, false, 'Внутрішня помилка сервера');
}