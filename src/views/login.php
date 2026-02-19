<?php
/**
 * Сторінка входу та реєстрації
 *
 * Дві форми: логін та реєстрація з вибором команди.
 * Після входу — збір analytics даних та редірект.
 *
 * @package QuestGame\Views
 */
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вхід — Квест «Безпека в Інтернеті»</title>
    <script src="https://cdn.tailwindcss.com" id="tailwind-cdn"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" id="bootstrap-cdn" disabled>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body id="page-body">

    <!-- Перемикач тем -->
    <div id="theme-switcher" class="theme-switcher">
        <label for="theme-select">🎨 Тема:</label>
        <select id="theme-select">
            <option value="tailwind">Tailwind CSS</option>
            <option value="bootstrap">Bootstrap</option>
            <option value="pure">Чистий CSS</option>
        </select>
    </div>

    <div class="login-container">
        <div class="login-card">

            <!-- Заголовок -->
            <div class="login-header">
                <h1>🛡️ Квест</h1>
                <h2>«Безпека в Інтернеті»</h2>
                <p class="login-subtitle">Перевір свої знання про онлайн-безпеку!</p>
            </div>

            <!-- Повідомлення -->
            <div id="auth-message" class="auth-message hidden"></div>

            <!-- Табки: Вхід / Реєстрація -->
            <div class="auth-tabs">
                <button class="auth-tab active" id="btn-tab-login" onclick="switchAuthTab('login')">🔑 Вхід</button>
                <button class="auth-tab" id="btn-tab-register" onclick="switchAuthTab('register')">📝 Реєстрація</button>
            </div>

            <!-- Форма входу -->
            <form id="login-form" class="auth-form" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label for="login-username">👤 Ім'я користувача</label>
                    <input type="text" id="login-username" placeholder="Введіть ваше ім'я" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="login-password">🔒 Пароль</label>
                    <input type="password" id="login-password" placeholder="Введіть пароль" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-primary btn-full" id="login-submit-btn">Увійти</button>
            </form>

            <!-- Форма реєстрації -->
            <form id="register-form" class="auth-form hidden" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label for="reg-username">👤 Ім'я користувача</label>
                    <input type="text" id="reg-username" placeholder="Придумайте ім'я (від 3 символів)" required minlength="3" maxlength="50" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="reg-password">🔒 Пароль</label>
                    <input type="password" id="reg-password" placeholder="Придумайте пароль (від 4 символів)" required minlength="4" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>🏁 Оберіть команду</label>
                    <div class="team-selector">
                        <label class="team-option team-1">
                            <input type="radio" name="team" value="1" required checked>
                            <span class="team-badge">🔵 Команда 1</span>
                        </label>
                        <label class="team-option team-2">
                            <input type="radio" name="team" value="2">
                            <span class="team-badge">🔴 Команда 2</span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn-primary btn-full" id="register-submit-btn">Зареєструватися</button>
            </form>

            <div class="login-footer">
                <p>🌐 Гра про безпеку в Інтернеті для учнів</p>
            </div>
        </div>
    </div>

    <script>
        window.QUEST_USER = null;
        window.QUEST_PAGE = 'login';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>