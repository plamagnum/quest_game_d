<?php
/**
 * Спільні ігрові функції
 *
 * Використовуються в api/quest.php та api/results.php
 * щоб уникнути дублювання коду.
 *
 * @package QuestGame\Helpers
 */

declare(strict_types=1);

/**
 * Отримати поточний стан гри з таблиці game_state
 *
 * Якщо рядок не існує — створює його з дефолтними значеннями.
 *
 * @param PDO $db PDO з'єднання
 * @return array Асоціативний масив стану гри
 */
function getGameState(PDO $db): array
{
    $stmt = $db->query('SELECT * FROM game_state WHERE id = 1');
    $state = $stmt->fetch();

    if (!$state) {
        $db->exec('INSERT INTO game_state (id, is_game_active) VALUES (1, 0)');
        return [
            'id'                  => 1,
            'current_question_id' => null,
            'is_button_locked'    => 0,
            'locked_by_team'      => null,
            'locked_by_user_id'   => null,
            'is_game_active'      => 0,
        ];
    }

    return $state;
}

/**
 * Отримати рахунок по командах
 *
 * Повертає масив з двох елементів (Команда 1, Команда 2),
 * кожен з полями: team, total_points, correct_answers, total_answers.
 *
 * @param PDO $db PDO з'єднання
 * @return array [0 => дані команди 1, 1 => дані команди 2]
 */
function getTeamScores(PDO $db): array
{
    $stmt = $db->query(
        'SELECT u.team,
                COALESCE(SUM(a.points_earned), 0) AS total_points,
                COUNT(CASE WHEN a.is_correct = 1 THEN 1 END) AS correct_answers,
                COUNT(a.id) AS total_answers
         FROM users u
         LEFT JOIN answers a ON u.id = a.user_id
         WHERE u.role = "user"
         GROUP BY u.team
         ORDER BY u.team'
    );

    $rows = $stmt->fetchAll();

    // Завжди повертаємо обидві команди, навіть якщо в одній немає гравців
    $scores = [
        ['team' => 1, 'total_points' => 0, 'correct_answers' => 0, 'total_answers' => 0],
        ['team' => 2, 'total_points' => 0, 'correct_answers' => 0, 'total_answers' => 0],
    ];

    foreach ($rows as $row) {
        $index = (int)$row['team'] - 1;
        if (isset($scores[$index])) {
            $scores[$index] = [
                'team'            => (int)$row['team'],
                'total_points'    => (int)$row['total_points'],
                'correct_answers' => (int)$row['correct_answers'],
                'total_answers'   => (int)$row['total_answers'],
            ];
        }
    }

    return $scores;
}

/**
 * Отримати назви команд з таблиці settings
 *
 * @param PDO $db
 * @return array ['team1_name' => '...', 'team2_name' => '...']
 */
function getTeamNames(PDO $db): array
{
    $names = [
        'team1_name' => 'Команда 1',
        'team2_name' => 'Команда 2',
    ];

    try {
        $stmt = $db->query(
            "SELECT `key`, `value` FROM settings WHERE `key` IN ('team1_name', 'team2_name')"
        );
        foreach ($stmt->fetchAll() as $row) {
            $names[$row['key']] = $row['value'];
        }
    } catch (PDOException $e) {
        // Таблиця може не існувати — повертаємо дефолтні
    }

    return $names;
}