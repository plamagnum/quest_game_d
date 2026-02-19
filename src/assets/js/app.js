/**
 * ============================================
 * Квест «Безпека в Інтернеті» — Frontend
 * ============================================
 *
 * Чистий JS (без фреймворків), fetch API.
 * Polling кожну 1-2 секунди для real-time оновлень.
 *
 * Глобальні змінні, встановлені у views:
 *   window.QUEST_USER — дані поточного користувача
 *   window.QUEST_PAGE — поточна сторінка (login|quest|admin|results)
 *
 * @package QuestGame\Frontend
 */

"use strict";

/** @type {number|null} ID інтервалу polling */
let pollingInterval = null;

/** @type {string|null} ID останнього відомого запитання (для відстеження зміни) */
let lastQuestionId = null;

/** @type {boolean} Чи вже відповів на поточне запитання */
let hasAnsweredCurrent = false;

/** @type {Array} Кеш даних аналітики для сортування/фільтрації */
let analyticsCache = [];

/** @type {string} Поточне поле сортування аналітики */
let analyticsSortField = '';

/** @type {boolean} Напрямок сортування */
let analyticsSortAsc = true;

// ================================================================
// ІНІЦІАЛІЗАЦІЯ
// ================================================================

document.addEventListener('DOMContentLoaded', function () {
    initThemeSwitcher();

    var page = window.QUEST_PAGE || 'login';

    if (page === 'login') {
        loadTeamNames(); // щоб у реєстрації показати назви команд
    } else if (page === 'quest') {
        collectAnalytics();
        loadTeamNames();
        startQuestPolling();
    } else if (page === 'admin') {
        collectAnalytics();
        loadTeamNames();
        startAdminPolling();
        loadQuestions();
        loadAnalytics();
        loadDetailedResults();
        loadAdjustments();
    } else if (page === 'results') {
        collectAnalytics();
        loadTeamNames();
        startResultsPolling();
    }
});

// ================================================================
// 1. ПЕРЕМИКАЧ ТЕМ
// ================================================================

/**
 * Ініціалізація перемикача тем.
 * Читає тему з localStorage і застосовує.
 */
function initThemeSwitcher() {
    var select = document.getElementById('theme-select');
    if (!select) return;

    var saved = localStorage.getItem('quest-theme') || 'tailwind';
    select.value = saved;
    applyTheme(saved);

    select.addEventListener('change', function () {
        localStorage.setItem('quest-theme', this.value);
        applyTheme(this.value);
    });
}

/**
 * Застосувати тему: ввімкнути/вимкнути CDN стилів
 * @param {string} theme — 'tailwind' | 'bootstrap' | 'pure'
 */
function applyTheme(theme) {
    var tw = document.getElementById('tailwind-cdn');
    var bs = document.getElementById('bootstrap-cdn');

    if (theme === 'tailwind') {
        if (tw) tw.disabled = false;
        if (bs) bs.disabled = true;
    } else if (theme === 'bootstrap') {
        if (tw) tw.disabled = true;
        if (bs) bs.disabled = false;
    } else {
        if (tw) tw.disabled = true;
        if (bs) bs.disabled = true;
    }
}

// ================================================================
// 2. АВТОРИЗАЦІЯ
// ================================================================

/**
 * Перемикання табів логін / реєстрація
 * @param {string} tab — 'login' | 'register'
 */
function switchAuthTab(tab) {
    var loginForm    = document.getElementById('login-form');
    var registerForm = document.getElementById('register-form');
    var tabLogin     = document.getElementById('btn-tab-login');
    var tabRegister  = document.getElementById('btn-tab-register');
    var msg          = document.getElementById('auth-message');

    if (!loginForm || !registerForm) return;

    if (tab === 'register') {
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        tabLogin.classList.remove('active');
        tabRegister.classList.add('active');
    } else {
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
        tabLogin.classList.add('active');
        tabRegister.classList.remove('active');
    }

    if (msg) {
        msg.classList.add('hidden');
        msg.textContent = '';
    }
}

/**
 * Обробка логіну
 * @param {Event} e
 */
function handleLogin(e) {
    e.preventDefault();

    var username = document.getElementById('login-username').value.trim();
    var password = document.getElementById('login-password').value;

    if (!username || !password) {
        showAuthMessage("Введіть ім'я та пароль", false);
        return;
    }

    var btn = document.getElementById('login-submit-btn');
    if (btn) btn.disabled = true;

    fetch('/api/auth.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: username, password: password })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            showAuthMessage('Вхід успішний! Перенаправлення...', true);
            setTimeout(function () {
                window.location.href = (data.data && data.data.role === 'admin') ? '/admin' : '/quest';
            }, 500);
        } else {
            showAuthMessage(data.message || 'Помилка входу', false);
            if (btn) btn.disabled = false;
        }
    })
    .catch(function () {
        showAuthMessage('Помилка з\'єднання з сервером', false);
        if (btn) btn.disabled = false;
    });
}

/**
 * Обробка реєстрації
 * @param {Event} e
 */
function handleRegister(e) {
    e.preventDefault();

    var username = document.getElementById('reg-username').value.trim();
    var password = document.getElementById('reg-password').value;
    var teamRadio = document.querySelector('input[name="team"]:checked');
    var team = teamRadio ? parseInt(teamRadio.value, 10) : 1;

    if (!username || !password) {
        showAuthMessage("Заповніть усі поля", false);
        return;
    }
    if (username.length < 3) {
        showAuthMessage("Ім'я має бути від 3 символів", false);
        return;
    }
    if (password.length < 4) {
        showAuthMessage("Пароль має бути від 4 символів", false);
        return;
    }

    var btn = document.getElementById('register-submit-btn');
    if (btn) btn.disabled = true;

    fetch('/api/auth.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: username, password: password, team: team })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            showAuthMessage('Реєстрація успішна! Перенаправлення...', true);
            setTimeout(function () {
                window.location.href = '/quest';
            }, 500);
        } else {
            showAuthMessage(data.message || 'Помилка реєстрації', false);
            if (btn) btn.disabled = false;
        }
    })
    .catch(function () {
        showAuthMessage('Помилка з\'єднання з сервером', false);
        if (btn) btn.disabled = false;
    });
}

/**
 * Вихід з системи
 */
function handleLogout() {
    fetch('/api/auth.php?action=logout')
    .then(function () {
        window.location.href = '/login';
    })
    .catch(function () {
        window.location.href = '/login';
    });
}

/**
 * Показати повідомлення на сторінці логіну
 * @param {string} msg — текст
 * @param {boolean} isSuccess — зелене чи червоне
 */
function showAuthMessage(msg, isSuccess) {
    var el = document.getElementById('auth-message');
    if (!el) return;
    el.textContent = msg;
    el.className = 'auth-message ' + (isSuccess ? 'msg-success' : 'msg-error');
    el.classList.remove('hidden');
}

// ================================================================
// 3. ЗБІР АНАЛІТИКИ
// ================================================================

/**
 * Зібрати дані про браузер/ОС клієнта та відправити на сервер
 */
function collectAnalytics() {
    var ua = navigator.userAgent;
    var os = detectOS(ua);
    var browserInfo = detectBrowser(ua);

    var payload = {
        os: os,
        browser: browserInfo.name,
        browser_version: browserInfo.version,
        screen_resolution: screen.width + 'x' + screen.height,
        language: navigator.language || navigator.userLanguage || ''
    };

    fetch('/api/analytics.php?action=collect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).catch(function (err) {
        console.warn('Analytics collect error:', err);
    });
}

/**
 * Визначити ОС з User-Agent
 * @param {string} ua
 * @returns {string}
 */
function detectOS(ua) {
    if (/Windows NT 10/i.test(ua))       return 'Windows 10/11';
    if (/Windows NT 6\.3/i.test(ua))     return 'Windows 8.1';
    if (/Windows NT 6\.2/i.test(ua))     return 'Windows 8';
    if (/Windows NT 6\.1/i.test(ua))     return 'Windows 7';
    if (/Windows/i.test(ua))             return 'Windows';
    if (/Mac OS X/i.test(ua))            return 'macOS';
    if (/Android/i.test(ua))             return 'Android';
    if (/iPhone|iPad|iPod/i.test(ua))    return 'iOS';
    if (/Linux/i.test(ua))              return 'Linux';
    if (/CrOS/i.test(ua))              return 'Chrome OS';
    return 'Невідома';
}

/**
 * Визначити браузер та його версію з User-Agent
 * @param {string} ua
 * @returns {{name: string, version: string}}
 */
function detectBrowser(ua) {
    var match;
    if ((match = ua.match(/Edg\/(\d+[\.\d]*)/)))        return { name: 'Edge', version: match[1] };
    if ((match = ua.match(/OPR\/(\d+[\.\d]*)/)))         return { name: 'Opera', version: match[1] };
    if ((match = ua.match(/YaBrowser\/(\d+[\.\d]*)/)))   return { name: 'Yandex', version: match[1] };
    if ((match = ua.match(/Chrome\/(\d+[\.\d]*)/)))      return { name: 'Chrome', version: match[1] };
    if ((match = ua.match(/Firefox\/(\d+[\.\d]*)/)))     return { name: 'Firefox', version: match[1] };
    if ((match = ua.match(/Safari\/(\d+[\.\d]*)/) && /Version\/(\d+[\.\d]*)/.test(ua))) {
        var ver = ua.match(/Version\/(\d+[\.\d]*)/);
        return { name: 'Safari', version: ver ? ver[1] : '' };
    }
    if (/MSIE|Trident/i.test(ua))                       return { name: 'IE', version: '' };
    return { name: 'Невідомий', version: '' };
}

// ================================================================
// 4. КВЕСТ — POLLING ТА ЛОГІКА ГРИ
// ================================================================

/**
 * Запустити polling стану гри (кожну 1 сек)
 */
function startQuestPolling() {
    pollQuestState();
    pollingInterval = setInterval(pollQuestState, 1000);
}

/**
 * Один запит стану гри
 */
function pollQuestState() {
    fetch('/api/quest.php?action=state')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        updateQuestUI(resp.data);
    })
    .catch(function (err) {
        console.warn('Quest poll error:', err);
    });
}

/**
 * Оновити UI квесту за даними стану
 * @param {Object} data — дані з api/quest.php?action=state
 */
function updateQuestUI(data) {
    var stateWaiting  = document.getElementById('state-waiting');
    var stateQuestion = document.getElementById('state-question');
    var stateGameover = document.getElementById('state-gameover');

    // Оновити рахунок у шапці
    var st1 = document.getElementById('score-team1');
    var st2 = document.getElementById('score-team2');
    if (st1 && data.scores && data.scores[0]) st1.textContent = data.scores[0].total_points;
    if (st2 && data.scores && data.scores[1]) st2.textContent = data.scores[1].total_points;
    if (data.team1_name) team1Name = data.team1_name;
    if (data.team2_name) team2Name = data.team2_name;
    applyTeamNames();
    // --- Гра не активна ---
    if (!data.is_game_active) {
        if (!data.current_question && !hasAnsweredCurrent) {
            // Ще не починалась або вже закінчилась
            if (stateWaiting)  stateWaiting.classList.remove('hidden');
            if (stateQuestion) stateQuestion.classList.add('hidden');
            if (stateGameover) stateGameover.classList.add('hidden');

            // Якщо є рахунок і хоч одна відповідь — показати gameover
            if (data.scores) {
                var totalAnswers = (data.scores[0] ? data.scores[0].total_answers : 0)
                                 + (data.scores[1] ? data.scores[1].total_answers : 0);
                if (totalAnswers > 0) {
                    if (stateWaiting)  stateWaiting.classList.add('hidden');
                    if (stateGameover) stateGameover.classList.remove('hidden');
                    renderFinalScores(data.scores);
                }
            }
        }
        return;
    }

    // --- Гра активна ---
    if (stateWaiting)  stateWaiting.classList.add('hidden');
    if (stateGameover) stateGameover.classList.add('hidden');
    if (stateQuestion) stateQuestion.classList.remove('hidden');

    // Перевірка зміни запитання
    var currentQId = data.current_question ? data.current_question.id : null;
    if (currentQId !== lastQuestionId) {
        lastQuestionId = currentQId;
        hasAnsweredCurrent = false;
        hideAnswerResult();
        hideOptions();
    }

    // Відобразити запитання
    if (data.current_question) {
        var qNum  = document.getElementById('question-number');
        var qText = document.getElementById('question-text');
        var qPts  = document.getElementById('question-points');
        if (qNum)  qNum.textContent = 'Запитання #' + data.current_question.id;
        if (qText) qText.textContent = data.current_question.question_text;
        if (qPts)  qPts.textContent = '🏆 ' + data.current_question.points + ' балів';
    }

    // Оновити стан кнопки
    updateBuzzButton(data);
}

/**
 * Оновити вигляд buzz-кнопки
 * @param {Object} data — дані стану гри
 */
function updateBuzzButton(data) {
    var buzzBtn    = document.getElementById('buzz-button');
    var buzzStatus = document.getElementById('buzz-status');
    var buzzContainer = document.getElementById('buzz-container');

    if (!buzzBtn) return;

    // Якщо вже відповів — сховати кнопку
    if (hasAnsweredCurrent) {
        if (buzzContainer) buzzContainer.classList.add('hidden');
        return;
    }
    if (buzzContainer) buzzContainer.classList.remove('hidden');

    if (!data.is_button_locked) {
        // Кнопка вільна — можна натискати
        buzzBtn.disabled = false;
        buzzBtn.className = 'buzz-button';
        buzzBtn.querySelector('.buzz-icon').textContent = '🖐️';
        buzzBtn.querySelector('.buzz-text').innerHTML = 'Я знаю<br>відповідь!';
        if (buzzStatus) buzzStatus.textContent = 'Натисніть кнопку, якщо знаєте відповідь!';
    } else if (data.locked_by_user_id === data.my_user_id) {
        // Я натиснув — показати варіанти
        buzzBtn.disabled = true;
        buzzBtn.className = 'buzz-button my-team-locked';
        buzzBtn.querySelector('.buzz-icon').textContent = '✅';
        buzzBtn.querySelector('.buzz-text').innerHTML = 'Ви<br>натиснули!';
        if (buzzStatus) buzzStatus.textContent = 'Оберіть відповідь нижче!';

        // Показати варіанти тільки натиснувшему (якщо увімкнено)
        if (data.current_question && data.current_question.options) {
            if (data.show_options === false) {
                hideOptions();
                var oralMsg = document.getElementById('oral-answer-msg');
                if (!oralMsg) {
                    oralMsg = document.createElement('div');
                    oralMsg.id = 'oral-answer-msg';
                    oralMsg.style.cssText = 'margin-top:12px;padding:12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;text-align:center;font-weight:bold;';
                    oralMsg.textContent = '🗣️ Відповідайте усно! Адміністратор оцінить вашу відповідь.';
                    var optContainer = document.getElementById('options-container');
                    if (optContainer && optContainer.parentNode) {
                        optContainer.parentNode.insertBefore(oralMsg, optContainer);
                    }
                }
                oralMsg.style.display = '';
            } else {
                var existingMsg = document.getElementById('oral-answer-msg');
                if (existingMsg) existingMsg.style.display = 'none';
                showOptions(data.current_question.options);
            }
        }
    } else if (data.locked_by_team === data.my_team) {
        // Мій тімейт натиснув
        buzzBtn.disabled = true;
        buzzBtn.className = 'buzz-button my-team-locked';
        buzzBtn.querySelector('.buzz-icon').textContent = '👍';
        buzzBtn.querySelector('.buzz-text').innerHTML = 'Ваша<br>команда!';
        if (buzzStatus) {
            buzzStatus.textContent = data.locked_by_username
                ? data.locked_by_username + ' відповідає...'
                : 'Ваш тімейт відповідає...';
        }
    } else {
        // Інша команда натиснула — заблоковано
        buzzBtn.disabled = true;
        buzzBtn.className = 'buzz-button locked';
        buzzBtn.querySelector('.buzz-icon').textContent = '🔒';
        buzzBtn.querySelector('.buzz-text').innerHTML = 'Заблоко-<br>вано';
        if (buzzStatus) {
            buzzStatus.textContent = 'Команда ' + data.locked_by_team + ' відповідає...';
        }
    }
}

/**
 * Натиснути кнопку buzz
 */
function handleBuzz() {
    var buzzBtn = document.getElementById('buzz-button');
    if (buzzBtn) buzzBtn.disabled = true;

    fetch('/api/quest.php?action=buzz', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (!data.success) {
            alert(data.message);
            if (buzzBtn) buzzBtn.disabled = false;
        }
        // UI оновиться через polling
    })
    .catch(function (err) {
        console.error('Buzz error:', err);
        if (buzzBtn) buzzBtn.disabled = false;
    });
}

/**
 * Показати варіанти відповідей
 * @param {Array} options — масив рядків
 */
function showOptions(options) {
    var container = document.getElementById('options-container');
    var grid      = document.getElementById('options-grid');
    if (!container || !grid) return;

    // Не перерисовувати, якщо вже показані
    if (!container.classList.contains('hidden') && grid.children.length > 0) return;

    grid.innerHTML = '';

    for (var i = 0; i < options.length; i++) {
        var btn = document.createElement('button');
        btn.className = 'option-btn';
        btn.textContent = options[i];
        btn.setAttribute('data-index', i.toString());
        btn.addEventListener('click', function () {
            handleAnswer(parseInt(this.getAttribute('data-index'), 10));
        });
        grid.appendChild(btn);
    }

    container.classList.remove('hidden');
}

/** Сховати варіанти */
function hideOptions() {
    var container = document.getElementById('options-container');
    if (container) container.classList.add('hidden');
}

/**
 * Відправити відповідь
 * @param {number} optionIndex — індекс обраного варіанту
 */
function handleAnswer(optionIndex) {
    // Заблокувати всі кнопки
    var buttons = document.querySelectorAll('.option-btn');
    buttons.forEach(function (btn) { btn.disabled = true; });

    fetch('/api/quest.php?action=answer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ selected_option: optionIndex })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            hasAnsweredCurrent = true;
            var correctIdx = data.data ? data.data.correct_answer : -1;

            // Підсвітити кнопки
            buttons.forEach(function (btn) {
                var idx = parseInt(btn.getAttribute('data-index'), 10);
                if (idx === correctIdx) {
                    btn.classList.add('correct');
                } else if (idx === optionIndex && !data.data.is_correct) {
                    btn.classList.add('wrong');
                }
            });

            // Показати результат
            showAnswerResult(data.data.is_correct, data.data.points_earned, data.message);

            // Сховати buzz-кнопку
            var buzzC = document.getElementById('buzz-container');
            if (buzzC) buzzC.classList.add('hidden');
        } else {
            alert(data.message);
            buttons.forEach(function (btn) { btn.disabled = false; });
        }
    })
    .catch(function (err) {
        console.error('Answer error:', err);
        buttons.forEach(function (btn) { btn.disabled = false; });
    });
}

/**
 * Показати результат відповіді
 * @param {boolean} isCorrect
 * @param {number} points
 * @param {string} message
 */
function showAnswerResult(isCorrect, points, message) {
    var container = document.getElementById('answer-result');
    var icon      = document.getElementById('result-icon');
    var text      = document.getElementById('result-text');
    var pts       = document.getElementById('result-points');

    if (!container) return;

    if (icon)  icon.textContent = isCorrect ? '🎉' : '😔';
    if (text)  text.textContent = message;
    if (pts)   pts.textContent = isCorrect ? ('+' + points + ' балів') : '0 балів';

    container.classList.remove('hidden');
}

/** Сховати результат */
function hideAnswerResult() {
    var container = document.getElementById('answer-result');
    if (container) container.classList.add('hidden');
}

/**
 * Відрендерити фінальний рахунок
 * @param {Array} scores
 */
function renderFinalScores(scores) {
    var container = document.getElementById('final-scores');
    if (!container) return;

    var t1 = scores[0] || { total_points: 0 };
    var t2 = scores[1] || { total_points: 0 };

    container.innerHTML =
        '<div class="score-card team1-card"><span>🔵 Команда 1</span><strong>' + t1.total_points + '</strong></div>' +
        '<div class="score-vs" style="color:var(--dark);font-size:1.5rem;">VS</div>' +
        '<div class="score-card team2-card"><span>🔴 Команда 2</span><strong>' + t2.total_points + '</strong></div>';
}

// ================================================================
// 5. АДМІН-ПАНЕЛЬ
// ================================================================

/**
 * Перемикання вкладок адмін-панелі
 * @param {string} tabName — 'game' | 'questions' | 'analytics' | 'results'
 */
function switchAdminTab(tabName) {
    var sections = ['game', 'questions', 'analytics', 'results'];
    var nav = document.getElementById('admin-tabs-nav');

    sections.forEach(function (name) {
        var section = document.getElementById('tab-' + name);
        if (section) {
            if (name === tabName) {
                section.classList.remove('hidden');
            } else {
                section.classList.add('hidden');
            }
        }
    });

    // Оновити активну вкладку
    if (nav) {
        var tabs = nav.querySelectorAll('.admin-tab');
        tabs.forEach(function (tab) {
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
    }

    // При переході — оновити дані
    if (tabName === 'questions')  loadQuestions();
    if (tabName === 'analytics') loadAnalytics();
    if (tabName === 'results')   loadDetailedResults();
}

/**
 * Polling стану гри для адмін-панелі (кожні 2 сек)
 */
function startAdminPolling() {
    pollAdminState();
    pollingInterval = setInterval(pollAdminState, 2000);
}

/**
 * Один запит стану гри для адмін-панелі
 */
function pollAdminState() {
    fetch('/api/quest.php?action=state')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        var d = resp.data;

        var status    = document.getElementById('admin-game-status');
        var currentQ  = document.getElementById('admin-current-q');
        var lockSt    = document.getElementById('admin-lock-status');
        var lockTeam  = document.getElementById('admin-locked-team');
        var lockUser  = document.getElementById('admin-locked-user');
        var scoreT1   = document.getElementById('admin-score-t1');
        var scoreT2   = document.getElementById('admin-score-t2');

        if (status)   status.textContent = d.is_game_active ? '🟢 Активна' : '🔴 Неактивна';
        if (currentQ) {
            currentQ.textContent = d.current_question
                ? ('#' + d.current_question.id + ': ' + d.current_question.question_text.substring(0, 60) + '...')
                : '—';
        }
        if (lockSt)   lockSt.textContent = d.is_button_locked ? '🔒 Так' : '🔓 Ні';
        if (lockTeam) lockTeam.textContent = d.locked_by_team ? ('Команда ' + d.locked_by_team) : '—';
        if (lockUser) lockUser.textContent = d.locked_by_username || '—';

        if (scoreT1 && d.scores && d.scores[0]) scoreT1.textContent = d.scores[0].total_points;
        if (scoreT2 && d.scores && d.scores[1]) scoreT2.textContent = d.scores[1].total_points;

        // Оновити стан перемикача show_options
        var toggleOpts = document.getElementById('toggle-show-options');
        if (toggleOpts && d.show_options !== undefined) {
            toggleOpts.checked = (d.show_options === true);
        }
    })
    .catch(function (err) {
        console.warn('Admin poll error:', err);
    });
}

/**
 * Виконати адмін-дію (start, stop, next, reset)
 * @param {string} action
 */
function adminAction(action) {
    fetch('/api/quest.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        alert(data.message);
        pollAdminState();
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}

// ----------------------------------------------------------------
// 5.1 CRUD ЗАПИТАНЬ
// ----------------------------------------------------------------

/**
 * Завантажити список запитань
 */
function loadQuestions() {
    fetch('/api/questions.php?action=list')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        renderQuestionsTable(resp.data.questions);
    })
    .catch(function (err) {
        console.error('Load questions error:', err);
    });
}

/**
 * Відрендерити таблицю запитань
 * @param {Array} questions
 */
function renderQuestionsTable(questions) {
    var tbody = document.getElementById('questions-tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    questions.forEach(function (q, idx) {
        var options = q.options || [];
        var optionsHtml = options.map(function (o, i) {
            var marker = (i === q.correct_answer) ? '<strong>✅ ' + escapeHtml(o) + '</strong>' : escapeHtml(o);
            return marker;
        }).join('<br>');

        var correctText = options[q.correct_answer] || '—';

        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + (idx + 1) + '</td>' +
            '<td>' + escapeHtml(q.question_text) + '</td>' +
            '<td style="font-size:12px">' + optionsHtml + '</td>' +
            '<td>' + escapeHtml(correctText) + '</td>' +
            '<td>' + q.points + '</td>' +
            '<td>' + (q.is_active ? '✅' : '❌') + '</td>' +
            '<td>' +
                '<button class="btn-primary btn-sm" onclick="editQuestion(' + q.id + ')">✏️</button> ' +
                '<button class="btn-danger btn-sm" onclick="deleteQuestion(' + q.id + ')">🗑️</button>' +
            '</td>';
        tbody.appendChild(tr);
    });
}

/**
 * Обробка форми створення/оновлення запитання
 * @param {Event} e
 */
function handleQuestionSubmit(e) {
    e.preventDefault();

    var editId = document.getElementById('q-edit-id').value;
    var text   = document.getElementById('q-text').value.trim();

    var options = [];
    for (var i = 0; i < 4; i++) {
        var val = document.getElementById('q-opt-' + i).value.trim();
        if (val) options.push(val);
    }

    var correct = parseInt(document.getElementById('q-correct').value, 10);
    var points  = parseInt(document.getElementById('q-points').value, 10);

    if (!text) { alert('Введіть текст запитання'); return; }
    if (options.length < 2) { alert('Мінімум 2 варіанти'); return; }

    var payload = {
        question_text: text,
        options: options,
        correct_answer: correct,
        points: points
    };

    var url;
    if (editId) {
        payload.id = parseInt(editId, 10);
        payload.is_active = 1;
        url = '/api/questions.php?action=update';
    } else {
        url = '/api/questions.php?action=create';
    }

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        alert(data.message);
        if (data.success) {
            resetQuestionForm();
            loadQuestions();
        }
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}

/**
 * Заповнити форму для редагування
 * @param {number} id
 */
function editQuestion(id) {
    fetch('/api/questions.php?action=list')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;

        var q = resp.data.questions.find(function (item) { return item.id === id; });
        if (!q) { alert('Запитання не знайдено'); return; }

        document.getElementById('q-edit-id').value = q.id;
        document.getElementById('q-text').value = q.question_text;
        document.getElementById('q-correct').value = q.correct_answer;
        document.getElementById('q-points').value = q.points;
        document.getElementById('q-form-title').textContent = '✏️ Редагування #' + q.id;

        var options = q.options || [];
        for (var i = 0; i < 4; i++) {
            var input = document.getElementById('q-opt-' + i);
            if (input) input.value = options[i] || '';
        }

        // Скролити до форми
        document.getElementById('question-form-card').scrollIntoView({ behavior: 'smooth' });
    });
}

/**
 * Видалити запитання
 * @param {number} id
 */
function deleteQuestion(id) {
    if (!confirm('Видалити запитання #' + id + '?')) return;

    fetch('/api/questions.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        alert(data.message);
        if (data.success) loadQuestions();
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}

/**
 * Скинути форму запитань
 */
function resetQuestionForm() {
    document.getElementById('q-edit-id').value = '';
    document.getElementById('q-text').value = '';
    document.getElementById('q-correct').value = '0';
    document.getElementById('q-points').value = '10';
    document.getElementById('q-form-title').textContent = '➕ Додати запитання';

    for (var i = 0; i < 4; i++) {
        var input = document.getElementById('q-opt-' + i);
        if (input) input.value = '';
    }
}

// ----------------------------------------------------------------
// 5.2 АНАЛІТИКА
// ----------------------------------------------------------------

/**
 * Завантажити аналітику користувачів
 */
function loadAnalytics() {
    fetch('/api/analytics.php?action=list')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        analyticsCache = resp.data.analytics || [];
        renderAnalyticsTable(analyticsCache);
    })
    .catch(function (err) {
        console.error('Load analytics error:', err);
    });
}

/**
 * Відрендерити таблицю аналітики
 * @param {Array} data
 */
function renderAnalyticsTable(data) {
    var tbody = document.getElementById('analytics-tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    data.forEach(function (row) {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + escapeHtml(row.username || '') + '</td>' +
            '<td>Команда ' + (row.team || '?') + '</td>' +
            '<td>' + escapeHtml(row.os || '') + '</td>' +
            '<td>' + escapeHtml(row.browser || '') + ' ' + escapeHtml(row.browser_version || '') + '</td>' +
            '<td>' + escapeHtml(row.screen_resolution || '') + '</td>' +
            '<td>' + escapeHtml(row.language || '') + '</td>' +
            '<td>' + escapeHtml(row.ip_address || '') + '</td>' +
            '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escapeHtml(row.user_agent || '') + '">' + escapeHtml(row.user_agent || '') + '</td>' +
            '<td>' + escapeHtml(row.created_at || '') + '</td>';
        tbody.appendChild(tr);
    });
}

/**
 * Фільтрація аналітики по імені
 */
function filterAnalytics() {
    var filter = document.getElementById('analytics-filter');
    if (!filter) return;
    var val = filter.value.toLowerCase();

    var filtered = analyticsCache.filter(function (row) {
        return (row.username || '').toLowerCase().indexOf(val) !== -1;
    });

    renderAnalyticsTable(filtered);
}

/**
 * Сортування аналітики
 * @param {string} field — поле для сортування
 */
function sortAnalytics(field) {
    if (analyticsSortField === field) {
        analyticsSortAsc = !analyticsSortAsc;
    } else {
        analyticsSortField = field;
        analyticsSortAsc = true;
    }

    analyticsCache.sort(function (a, b) {
        var va = (a[field] || '').toString().toLowerCase();
        var vb = (b[field] || '').toString().toLowerCase();
        if (va < vb) return analyticsSortAsc ? -1 : 1;
        if (va > vb) return analyticsSortAsc ? 1 : -1;
        return 0;
    });

    renderAnalyticsTable(analyticsCache);
}

// ================================================================
// 6. РЕЗУЛЬТАТИ
// ================================================================

/**
 * Polling результатів (кожні 2 сек)
 */
function startResultsPolling() {
    loadScoreboard();
    loadResultsDetailed();
    pollingInterval = setInterval(function () {
        loadScoreboard();
        loadResultsDetailed();
    }, 2000);
}

/**
 * Завантажити рахунок по командах
 */
function loadScoreboard() {
    fetch('/api/results.php?action=scoreboard')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        var scores = resp.data.scores;

        // Результати — сторінка results
        var rs1  = document.getElementById('result-score-t1');
        var rs2  = document.getElementById('result-score-t2');
        var rc1  = document.getElementById('result-correct-t1');
        var rc2  = document.getElementById('result-correct-t2');
        var rt1  = document.getElementById('result-total-t1');
        var rt2  = document.getElementById('result-total-t2');

        if (rs1 && scores[0]) rs1.textContent = scores[0].total_points;
        if (rs2 && scores[1]) rs2.textContent = scores[1].total_points;
        if (rc1 && scores[0]) rc1.textContent = scores[0].correct_answers;
        if (rc2 && scores[1]) rc2.textContent = scores[1].correct_answers;
        if (rt1 && scores[0]) rt1.textContent = scores[0].total_answers;
        if (rt2 && scores[1]) rt2.textContent = scores[1].total_answers;

        // Адмін — вкладка результатів
        var ar1 = document.getElementById('res-score-t1');
        var ar2 = document.getElementById('res-score-t2');
        if (ar1 && scores[0]) ar1.textContent = scores[0].total_points;
        if (ar2 && scores[1]) ar2.textContent = scores[1].total_points;
    })
    .catch(function (err) {
        console.warn('Scoreboard error:', err);
    });
}

/**
 * Завантажити детальну таблицю відповідей (сторінка results)
 */
function loadResultsDetailed() {
    fetch('/api/results.php?action=detailed')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        var answers = resp.data.answers || [];

        var tbody = document.getElementById('results-tbody');
        var empty = document.getElementById('results-empty');

        if (!tbody) return;

        tbody.innerHTML = '';

        if (answers.length === 0) {
            if (empty) empty.classList.remove('hidden');
            return;
        }
        if (empty) empty.classList.add('hidden');

        answers.forEach(function (a) {
            var tr = document.createElement('tr');
            var badge = a.is_correct == 1
                ? '<span class="badge-correct">✅ Так</span>'
                : '<span class="badge-wrong">❌ Ні</span>';

            tr.innerHTML =
                '<td>' + escapeHtml(a.username) + '</td>' +
                '<td>Команда ' + a.team + '</td>' +
                '<td>' + escapeHtml(a.question_text || '').substring(0, 50) + '...</td>' +
                '<td>' + escapeHtml(a.selected_text || '') + '</td>' +
                '<td>' + escapeHtml(a.correct_text || '') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td>' + a.points_earned + '</td>' +
                '<td>' + escapeHtml(a.answered_at || '') + '</td>';
            tbody.appendChild(tr);
        });
    })
    .catch(function (err) {
        console.warn('Results detailed error:', err);
    });
}

/**
 * Завантажити детальну таблицю для адмін-панелі
 */
function loadDetailedResults() {
    loadScoreboard();

    fetch('/api/results.php?action=detailed')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        var answers = resp.data.answers || [];

        var tbody = document.getElementById('results-detail-tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        answers.forEach(function (a) {
            var tr = document.createElement('tr');
            var badge = a.is_correct == 1
                ? '<span class="badge-correct">✅</span>'
                : '<span class="badge-wrong">❌</span>';

            tr.innerHTML =
                '<td>' + escapeHtml(a.username) + '</td>' +
                '<td>' + a.team + '</td>' +
                '<td>' + escapeHtml(a.question_text || '').substring(0, 40) + '...</td>' +
                '<td>' + escapeHtml(a.selected_text || '') + '</td>' +
                '<td>' + escapeHtml(a.correct_text || '') + '</td>' +
                '<td>' + badge + '</td>' +
                '<td>' + a.points_earned + '</td>' +
                '<td>' + escapeHtml(a.answered_at || '') + '</td>';
            tbody.appendChild(tr);
        });
    })
    .catch(function (err) {
        console.warn('Admin results error:', err);
    });
}

// ================================================================
// 7. УТИЛІТИ
// ================================================================

/**
 * XSS-безпечне екранування HTML
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// ================================================================
// 8. НАЗВИ КОМАНД
// ================================================================

/** @type {string} Назва команди 1 */
var team1Name = 'Команда 1';

/** @type {string} Назва команди 2 */
var team2Name = 'Команда 2';

/**
 * Завантажити назви команд з сервера
 */
function loadTeamNames() {
    fetch('/api/settings.php?action=get')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success || !resp.data || !resp.data.settings) return;

        team1Name = resp.data.settings.team1_name || 'Команда 1';
        team2Name = resp.data.settings.team2_name || 'Команда 2';

        applyTeamNames();

        // Заповнити форму в адмінці
        var input1 = document.getElementById('team1-name-input');
        var input2 = document.getElementById('team2-name-input');
        if (input1) input1.value = team1Name;
        if (input2) input2.value = team2Name;
    })
    .catch(function (err) {
        console.warn('Load team names error:', err);
    });
}

/**
 * Застосувати назви команд до всіх елементів на сторінці
 */
function applyTeamNames() {
    // Шапка квесту — міні-рахунок
    var miniT1 = document.querySelector('.score-team1-mini');
    var miniT2 = document.querySelector('.score-team2-mini');
    if (miniT1) miniT1.innerHTML = '🔵 ' + escapeHtml(team1Name) + ': <strong id="score-team1">' + (document.getElementById('score-team1')?.textContent || '0') + '</strong>';
    if (miniT2) miniT2.innerHTML = '<strong id="score-team2">' + (document.getElementById('score-team2')?.textContent || '0') + '</strong> 🔴 ' + escapeHtml(team2Name);

    // Сторінка результатів
    var resName1 = document.querySelector('.team1-big .score-team-name');
    var resName2 = document.querySelector('.team2-big .score-team-name');
    if (resName1) resName1.textContent = '🔵 ' + team1Name;
    if (resName2) resName2.textContent = '🔴 ' + team2Name;

    // Адмін — score cards
    var cards1 = document.querySelectorAll('.team1-card span');
    var cards2 = document.querySelectorAll('.team2-card span');
    cards1.forEach(function (el) { el.textContent = '🔵 ' + team1Name; });
    cards2.forEach(function (el) { el.textContent = '🔴 ' + team2Name; });

    // Сторінка реєстрації — вибір команди
    var badge1 = document.querySelector('.team-1 .team-badge');
    var badge2 = document.querySelector('.team-2 .team-badge');
    if (badge1) badge1.textContent = '🔵 ' + team1Name;
    if (badge2) badge2.textContent = '🔴 ' + team2Name;
}

/**
 * Обробка форми зміни назв команд (адмін)
 * @param {Event} e
 */
function handleTeamNamesSubmit(e) {
    e.preventDefault();

    var name1 = document.getElementById('team1-name-input').value.trim();
    var name2 = document.getElementById('team2-name-input').value.trim();

    if (!name1 || !name2) {
        alert('Введіть назви обох команд');
        return;
    }

    fetch('/api/settings.php?action=update_teams', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ team1_name: name1, team2_name: name2 })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            team1Name = name1;
            team2Name = name2;
            applyTeamNames();
            alert('✅ Назви команд оновлено!');
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}
// ================================================================
// 9. РУЧНЕ КЕРУВАННЯ БАЛАМИ
// ================================================================

/**
 * Обробка форми зміни балів (адмін)
 * @param {Event} e
 */
function handleAdjustScore(e) {
    e.preventDefault();

    var team   = parseInt(document.getElementById('adjust-team').value, 10);
    var points = parseInt(document.getElementById('adjust-points').value, 10);
    var reason = document.getElementById('adjust-reason').value.trim();

    if (team !== 1 && team !== 2 || isNaN(points) || points === 0) {
        alert('Вкажіть команду та ненульові бали');
        return;
    }
    if (Math.abs(points) > 1000) {
        alert('Максимум ±1000 балів за раз');
        return;
    }

    fetch('/api/settings.php?action=adjust_score', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ team: team, points: points, reason: reason })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            document.getElementById('adjust-points').value = '';
            document.getElementById('adjust-reason').value = '';
            loadAdjustments();
            pollAdminState();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}

/**
 * Швидке коригування балів (кнопки +5/-5 тощо)
 * @param {number} team  — 1 або 2
 * @param {number} points — кількість балів (+ або -)
 */
function quickAdjust(team, points) {
    fetch('/api/settings.php?action=adjust_score', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ team: team, points: points, reason: 'Швидке коригування' })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            loadAdjustments();
            pollAdminState();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}

/**
 * Завантажити та відобразити таблицю коригувань
 */
function loadAdjustments() {
    fetch('/api/settings.php?action=adjustments')
    .then(function (res) { return res.json(); })
    .then(function (resp) {
        if (!resp.success) return;
        renderAdjustmentsTable(resp.data.adjustments || []);
    })
    .catch(function (err) {
        console.warn('Load adjustments error:', err);
    });
}

/**
 * Відрендерити таблицю коригувань
 * @param {Array} adjustments
 */
function renderAdjustmentsTable(adjustments) {
    var tbody = document.getElementById('adjustments-tbody');
    if (!tbody) return;

    if (adjustments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888">Немає коригувань</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    adjustments.forEach(function (adj) {
        var teamLabel = adj.team === 1 ? '🔵 Команда 1' : '🔴 Команда 2';
        var pointsStr = adj.points > 0 ? ('+' + adj.points) : String(adj.points);
        var pointsStyle = adj.points > 0 ? 'color:#16a34a;font-weight:bold' : 'color:#dc2626;font-weight:bold';

        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + teamLabel + '</td>' +
            '<td style="' + pointsStyle + '">' + pointsStr + '</td>' +
            '<td>' + escapeHtml(adj.reason || '—') + '</td>' +
            '<td>' + escapeHtml(adj.admin_username || '') + '</td>' +
            '<td>' + escapeHtml(adj.created_at || '') + '</td>' +
            '<td><button class="btn-danger btn-sm" onclick="deleteAdjustment(' + adj.id + ')">🗑️</button></td>';
        tbody.appendChild(tr);
    });
}

/**
 * Видалити одне коригування
 * @param {number} id
 */
function deleteAdjustment(id) {
    if (!confirm('Видалити це коригування?')) return;

    fetch('/api/settings.php?action=delete_adjust', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            loadAdjustments();
            pollAdminState();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}

/**
 * Видалити всі коригування
 */
function clearAllAdjustments() {
    if (!confirm('Видалити всі коригування балів?')) return;

    fetch('/api/settings.php?action=clear_adjustments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            loadAdjustments();
            pollAdminState();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
    });
}

// ================================================================
// 10. ПЕРЕМИКАЧ ПОКАЗУ ВАРІАНТІВ ВІДПОВІДЕЙ
// ================================================================

/**
 * Обробка перемикача show_options (адмін)
 */
function handleToggleOptions() {
    var toggle = document.getElementById('toggle-show-options');
    if (!toggle) return;

    var showOptions = toggle.checked;

    fetch('/api/settings.php?action=toggle_options', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ show_options: showOptions })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (!data.success) {
            alert('❌ ' + data.message);
            toggle.checked = !showOptions; // відкотити
        }
    })
    .catch(function (err) {
        alert('Помилка: ' + err.message);
        toggle.checked = !showOptions;
    });
}
