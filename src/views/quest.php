<?php
/**
 * Сторінка квесту для учня
 *
 * Показує поточне запитання, велику кнопку "Я знаю відповідь!",
 * варіанти відповідей, стан гри та міні-рахунок.
 * Оновлюється через polling кожну секунду.
 *
 * @package QuestGame\Views
 */

// Допоміжна функція для екранування в HTML
function escHtml(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Квест — Безпека в Інтернеті</title>
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
    <header class="quest-header">
        <div class="header-left">
            <h1>🛡️ Квест: Безпека в Інтернеті</h1>
        </div>
        <div class="header-center">
            <div class="scoreboard-mini">
                <span class="score-team1-mini">🔵 <strong id="score-team1">0</strong></span>
                <span class="score-sep">:</span>
                <span class="score-team2-mini"><strong id="score-team2">0</strong> 🔴</span>
            </div>
        </div>
        <div class="header-right">
            <span class="user-info">👤 <?= escHtml($_SESSION['username'] ?? '') ?> (Команда <?= (int)($_SESSION['team'] ?? 0) ?>)</span>
            <a href="/results" class="btn-link">📊 Результати</a>
            <button onclick="handleLogout()" class="btn-logout">🚪 Вийти</button>
        </div>
    </header>

    <!-- Основний контент -->
    <main class="quest-main">

        <!-- ===== Стан: Очікування початку ===== -->
        <div id="state-waiting" class="game-state">
            <div class="waiting-screen">
                <div class="waiting-icon">⏳</div>
                <h2>Очікування початку гри</h2>
                <p>Адміністратор ще не розпочав квест. Зачекайте...</p>
                <div class="pulse-dot"></div>
            </div>
        </div>

        <!-- ===== Стан: Активне запитання ===== -->
        <div id="state-question" class="game-state hidden">

            <!-- Блок запитання -->
            <div class="question-container">
                <div class="question-number" id="question-number">Запитання</div>
                <div class="question-text" id="question-text"></div>
                <div class="question-points" id="question-points"></div>
            </div>

            <!-- ВЕЛИКА КНОПКА BUZZ -->
            <div class="buzz-container" id="buzz-container">
                <button class="buzz-button" id="buzz-button" onclick="handleBuzz()">
                    <span class="buzz-icon">🖐️</span>
                    <span class="buzz-text">Я знаю<br>відповідь!</span>
                </button>
                <div class="buzz-status" id="buzz-status"></div>
            </div>

            <!-- Варіанти відповідей (з'являються після buzz) -->
            <div class="options-container hidden" id="options-container">
                <h3>Оберіть відповідь:</h3>
                <div class="options-grid" id="options-grid"></div>
            </div>

            <!-- Результат відповіді -->
            <div class="answer-result hidden" id="answer-result">
                <div class="result-icon" id="result-icon"></div>
                <div class="result-text" id="result-text"></div>
                <div class="result-points" id="result-points"></div>
            </div>
        </div>

        <!-- ===== Стан: Гра завершена ===== -->
        <div id="state-gameover" class="game-state hidden">
            <div class="gameover-screen">
                <div class="gameover-icon">🏆</div>
                <h2>Гру завершено!</h2>
                <div id="final-scores" class="final-scores"></div>
                <a href="/results" class="btn-primary">📊 Переглянути детальні результати</a>
            </div>
        </div>

    </main>

    <!-- Дані сесії для JS -->
    <script>
        window.QUEST_USER = {
            id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
            username: "<?= escHtml($_SESSION['username'] ?? '') ?>",
            team: <?= (int)($_SESSION['team'] ?? 0) ?>,
            role: "<?= escHtml($_SESSION['role'] ?? '') ?>"
        };
        window.QUEST_PAGE = 'quest';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>