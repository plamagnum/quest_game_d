<?php
/**
 * Сторінка результатів (доступна всім авторизованим)
 *
 * Показує рахунок по командах та детальну таблицю відповідей.
 * Оновлення кожні 2 секунди без перезавантаження.
 *
 * @package QuestGame\Views
 */

function escRes(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результати — Квест «Безпека в Інтернеті»</title>
    <script src="https://cdn.tailwindcss.com" id="tailwind-cdn"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" id="bootstrap-cdn" disabled>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body id="page-body">

    <!-- Перемикач тем -->
    <div id="theme-switcher" class="theme-switcher">
        <label for="theme-select">🎨</label>
        <select id="theme-select">
            <option value="tailwind">Tailwind</option>
            <option value="bootstrap">Bootstrap</option>
            <option value="pure">Чистий CSS</option>
        </select>
    </div>

    <!-- Хедер -->
    <header class="results-header">
        <h1>🏆 Таблиця результатів</h1>
        <div class="header-right">
            <a href="/quest" class="btn-link">🎮 До гри</a>
            <button onclick="handleLogout()" class="btn-logout">🚪 Вийти</button>
        </div>
    </header>

    <main class="results-main">

        <!-- Рахунок по командах -->
        <div class="results-scoreboard" id="results-scoreboard">
            <div class="score-big team1-big">
                <div class="score-team-name">🔵 Команда 1</div>
                <div class="score-value" id="result-score-t1">0</div>
                <div class="score-detail">
                    Вірних: <span id="result-correct-t1">0</span> /
                    Всього: <span id="result-total-t1">0</span>
                </div>
            </div>
            <div class="score-vs">VS</div>
            <div class="score-big team2-big">
                <div class="score-team-name">🔴 Команда 2</div>
                <div class="score-value" id="result-score-t2">0</div>
                <div class="score-detail">
                    Вірних: <span id="result-correct-t2">0</span> /
                    Всього: <span id="result-total-t2">0</span>
                </div>
            </div>
        </div>

        <!-- Індикатор live-оновлення -->
        <div class="live-indicator">
            <span class="live-dot"></span> Оновлюється автоматично
        </div>

        <!-- Детальна таблиця -->
        <div class="table-responsive">
            <table class="data-table" id="results-table">
                <thead>
                    <tr>
                        <th>👤 Гравець</th>
                        <th>🏁 Команда</th>
                        <th>❓ Запитання</th>
                        <th>📝 Обрана відповідь</th>
                        <th>✅ Правильна відповідь</th>
                        <th>✔️ Вірно?</th>
                        <th>💰 Бали</th>
                        <th>🕐 Час</th>
                    </tr>
                </thead>
                <tbody id="results-tbody"></tbody>
            </table>
            <p id="results-empty" class="empty-message">Ще немає відповідей</p>
        </div>

    </main>

    <!-- Дані сесії -->
    <script>
        window.QUEST_USER = {
            id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
            username: "<?= escRes($_SESSION['username'] ?? '') ?>",
            team: <?= (int)($_SESSION['team'] ?? 0) ?>,
            role: "<?= escRes($_SESSION['role'] ?? '') ?>"
        };
        window.QUEST_PAGE = 'results';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>