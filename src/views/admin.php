<?php
/**
 * Адмін-панель
 *
 * Вкладки:
 *   1. Керування грою — старт/стоп, наступне запитання, скидання
 *   2. Запитання — CRUD інтерфейс
 *   3. Аналітика — дані браузерів/ОС користувачів
 *   4. Результати — рахунок та детальна таблиця
 *
 * @package QuestGame\Views
 */

function escAdm(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Адмін-панель — Квест</title>
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
    <header class="admin-header">
        <h1>⚙️ Адмін-панель</h1>
        <div class="header-right">
            <span>👤 <?= escAdm($_SESSION['username'] ?? '') ?> (Адмін)</span>
            <button onclick="handleLogout()" class="btn-logout">🚪 Вийти</button>
        </div>
    </header>

    <!-- Навігація по вкладках -->
    <nav class="admin-tabs" id="admin-tabs-nav">
        <button class="admin-tab active" data-tab="game" onclick="switchAdminTab('game')">🎮 Гра</button>
        <button class="admin-tab" data-tab="questions" onclick="switchAdminTab('questions')">❓ Запитання</button>
        <button class="admin-tab" data-tab="analytics" onclick="switchAdminTab('analytics')">📊 Аналітика</button>
        <button class="admin-tab" data-tab="results" onclick="switchAdminTab('results')">🏆 Результати</button>
        <button class="admin-tab" data-tab="players" onclick="switchAdminTab('players')">👥 Гравці</button>
    </nav>

    <!-- ===== ВКЛАДКА: Керування грою ===== -->
    <section id="tab-game" class="admin-section">
        <h2>🎮 Керування грою</h2>

                <!-- Назви команд -->
        <div class="game-status-card" id="team-names-card" style="margin-bottom:20px">
            <h3>🏁 Назви команд</h3>
            <form id="team-names-form" onsubmit="handleTeamNamesSubmit(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="team1-name-input">🔵 Команда 1</label>
                        <input type="text" id="team1-name-input" placeholder="Назва команди 1" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="team2-name-input">🔴 Команда 2</label>
                        <input type="text" id="team2-name-input" placeholder="Назва команди 2" required maxlength="50">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="submit" class="btn-primary">💾 Зберегти</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Статус -->
        <div class="game-status-card" id="game-status-card">
            <h3>Поточний стан</h3>
            <p>Статус гри: <strong id="admin-game-status">Завантаження...</strong></p>
            <p>Поточне запитання: <strong id="admin-current-q">—</strong></p>
            <p>Кнопка заблокована: <strong id="admin-lock-status">Ні</strong></p>
            <p>Заблокувала команда: <strong id="admin-locked-team">—</strong></p>
            <p>Заблокував гравець: <strong id="admin-locked-user">—</strong></p>
        </div>

        <!-- Кнопки керування -->
        <div class="game-buttons">
            <button onclick="adminAction('start')" class="btn-success" id="btn-start-game">▶️ Розпочати гру</button>
            <button onclick="adminAction('stop')" class="btn-danger" id="btn-stop-game">⏹️ Зупинити гру</button>
            <button onclick="adminAction('next')" class="btn-primary" id="btn-next-q">⏭️ Наступне запитання</button>
            <button onclick="adminAction('reset')" class="btn-warning" id="btn-reset-buzz">🔄 Скинути блокування</button>
            <button onclick="toggleOptions()" class="btn-primary" id="btn-toggle-options">👁️ Вар. відповідей: ВКЛ</button>
        </div>

        <!-- Обнулення рахунку -->
        <div style="margin-top:16px">
            <button onclick="resetScores()" class="btn-danger">🗑️ Обнулити рахунок</button>
        </div>

        <!-- Рахунок -->
        <div class="admin-scores">
            <h3>Рахунок</h3>
            <div class="scores-row">
                <div class="score-card team1-card">
                    <span>🔵 Команда 1</span>
                    <strong id="admin-score-t1">0</strong>
                </div>
                <div class="score-card team2-card">
                    <span>🔴 Команда 2</span>
                    <strong id="admin-score-t2">0</strong>
                </div>
            </div>
        </div>

        <!-- Коригування балів -->
        <div class="game-status-card" style="margin-top:20px">
            <h3>⚖️ Коригування балів</h3>
            <form id="adjust-score-form" onsubmit="handleAdjustScore(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="adjust-team">Команда</label>
                        <select id="adjust-team" required>
                            <option value="1">🔵 Команда 1</option>
                            <option value="2">🔴 Команда 2</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="adjust-points">Бали (+/-)</label>
                        <input type="number" id="adjust-points" placeholder="напр. 10 або -5" required min="-1000" max="1000">
                    </div>
                    <div class="form-group">
                        <label for="adjust-reason">Причина (необов'язково)</label>
                        <input type="text" id="adjust-reason" placeholder="Бонус за активність..." maxlength="255">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="submit" class="btn-primary">⚖️ Застосувати</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- ===== ВКЛАДКА: Запитання (CRUD) ===== -->
    <section id="tab-questions" class="admin-section hidden">
        <h2>❓ Управління запитаннями</h2>

        <!-- Форма створення/редагування -->
        <div class="question-form-card" id="question-form-card">
            <h3 id="q-form-title">➕ Додати запитання</h3>
            <form id="question-form" onsubmit="handleQuestionSubmit(event)">
                <input type="hidden" id="q-edit-id" value="">

                <div class="form-group">
                    <label for="q-text">Текст запитання</label>
                    <textarea id="q-text" rows="3" placeholder="Введіть текст запитання..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Варіанти відповідей</label>
                    <input type="text" id="q-opt-0" placeholder="Варіант 1" required class="mb-8">
                    <input type="text" id="q-opt-1" placeholder="Варіант 2" required class="mb-8">
                    <input type="text" id="q-opt-2" placeholder="Варіант 3" class="mb-8">
                    <input type="text" id="q-opt-3" placeholder="Варіант 4" class="mb-8">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="q-correct">Правильна відповідь</label>
                        <select id="q-correct" required>
                            <option value="0">Варіант 1</option>
                            <option value="1">Варіант 2</option>
                            <option value="2">Варіант 3</option>
                            <option value="3">Варіант 4</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="q-points">Бали</label>
                        <input type="number" id="q-points" value="10" min="1" max="100" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="q-submit-btn">💾 Зберегти</button>
                    <button type="button" class="btn-secondary" onclick="resetQuestionForm()">🔄 Скинути</button>
                </div>
            </form>
        </div>

        <!-- Таблиця запитань -->
        <div class="table-responsive">
            <table class="data-table" id="questions-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Запитання</th>
                        <th>Варіант��</th>
                        <th>Правильна</th>
                        <th>Бали</th>
                        <th>Активне</th>
                        <th>Дії</th>
                    </tr>
                </thead>
                <tbody id="questions-tbody"></tbody>
            </table>
        </div>
    </section>

    <!-- ===== ВКЛАДКА: Аналітика ===== -->
    <section id="tab-analytics" class="admin-section hidden">
        <h2>📊 Аналітика користувачів</h2>

        <div class="analytics-controls">
            <button onclick="loadAnalytics()" class="btn-primary">🔄 Оновити</button>
            <input type="text" id="analytics-filter" placeholder="🔍 Фільтр по імені..." oninput="filterAnalytics()">
        </div>

        <div class="table-responsive">
            <table class="data-table" id="analytics-table">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortAnalytics('username')">👤 Користувач ⇅</th>
                        <th class="sortable" onclick="sortAnalytics('team')">🏁 Команда ⇅</th>
                        <th class="sortable" onclick="sortAnalytics('os')">💻 ОС ⇅</th>
                        <th class="sortable" onclick="sortAnalytics('browser')">🌐 Браузер ⇅</th>
                        <th>📐 Роздільна здатність</th>
                        <th>🗣️ Мова</th>
                        <th>🌍 IP-адреса</th>
                        <th>📋 User-Agent</th>
                        <th class="sortable" onclick="sortAnalytics('created_at')">📅 Дата ⇅</th>
                    </tr>
                </thead>
                <tbody id="analytics-tbody"></tbody>
            </table>
        </div>
    </section>

    <!-- ===== ВКЛАДКА: Результати ===== -->
    <section id="tab-results" class="admin-section hidden">
        <h2>🏆 Результати гри</h2>

        <div class="scores-row" style="margin-bottom:20px">
            <div class="score-card team1-card">
                <span>🔵 Команда 1</span>
                <strong id="res-score-t1">0</strong>
            </div>
            <div class="score-card team2-card">
                <span>🔴 Команда 2</span>
                <strong id="res-score-t2">0</strong>
            </div>
        </div>

        <button onclick="loadDetailedResults()" class="btn-primary" style="margin-bottom:16px">🔄 Оновити</button>

        <div class="table-responsive">
            <table class="data-table" id="results-detail-table">
                <thead>
                    <tr>
                        <th>👤 Гравець</th>
                        <th>🏁 Команда</th>
                        <th>❓ Запитання</th>
                        <th>📝 Обрана</th>
                        <th>✅ Правильна</th>
                        <th>✔️ Вірно?</th>
                        <th>💰 Бали</th>
                        <th>🕐 Час</th>
                    </tr>
                </thead>
                <tbody id="results-detail-tbody"></tbody>
            </table>
        </div>
    </section>

    <!-- ===== ВКЛАДКА: Гравці ===== -->
    <section id="tab-players" class="admin-section hidden">
        <h2>👥 Управління гравцями</h2>

        <div class="analytics-controls">
            <button onclick="loadPlayers()" class="btn-primary">🔄 Оновити</button>
            <input type="text" id="players-filter" placeholder="🔍 Фільтр по імені..." oninput="filterPlayers()">
        </div>

        <div class="table-responsive">
            <table class="data-table" id="players-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>👤 Ім'я</th>
                        <th>🏁 Команда</th>
                        <th>🔑 Роль</th>
                        <th>📊 Статус</th>
                        <th>📅 Дата реєстрації</th>
                        <th>⚙️ Дії</th>
                    </tr>
                </thead>
                <tbody id="players-tbody"></tbody>
            </table>
        </div>
    </section>

    <!-- Дані сесії -->
    <script>
        window.QUEST_USER = {
            id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
            username: "<?= escAdm($_SESSION['username'] ?? '') ?>",
            team: <?= (int)($_SESSION['team'] ?? 0) ?>,
            role: "admin"
        };
        window.QUEST_PAGE = 'admin';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>