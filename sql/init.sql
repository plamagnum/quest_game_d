-- =============================================================
-- Ініціалізація БД квесту "Безпека в Інтернеті"
-- MySQL 8.0 | UTF-8
-- =============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ----------------------------
-- Таблиця: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `team`          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Команда 1, 2 = Команда 2',
    `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_team` (`team`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблиця: questions
-- ----------------------------
CREATE TABLE IF NOT EXISTS `questions` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_text`  TEXT NOT NULL,
    `options`        JSON NOT NULL COMMENT 'Масив варіантів відповідей',
    `correct_answer` TINYINT UNSIGNED NOT NULL COMMENT 'Індекс правильної відповіді (0-3)',
    `points`         INT UNSIGNED NOT NULL DEFAULT 10,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_sort`   (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблиця: answers
-- ----------------------------
CREATE TABLE IF NOT EXISTS `answers` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL,
    `question_id`     INT UNSIGNED NOT NULL,
    `selected_option` TINYINT UNSIGNED NOT NULL,
    `is_correct`      TINYINT(1) NOT NULL DEFAULT 0,
    `points_earned`   INT UNSIGNED NOT NULL DEFAULT 0,
    `answered_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_user_question` (`user_id`, `question_id`),
    INDEX `idx_user`     (`user_id`),
    INDEX `idx_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблиця: game_state (один рядок)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `game_state` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `current_question_id` INT UNSIGNED DEFAULT NULL,
    `is_button_locked`    TINYINT(1) NOT NULL DEFAULT 0,
    `locked_by_team`      TINYINT UNSIGNED DEFAULT NULL,
    `locked_by_user_id`   INT UNSIGNED DEFAULT NULL,
    `is_game_active`      TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`current_question_id`) REFERENCES `questions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`locked_by_user_id`)   REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблиця: user_analytics
-- ----------------------------
CREATE TABLE IF NOT EXISTS `user_analytics` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`           INT UNSIGNED NOT NULL,
    `os`                VARCHAR(100) DEFAULT NULL,
    `browser`           VARCHAR(100) DEFAULT NULL,
    `browser_version`   VARCHAR(50)  DEFAULT NULL,
    `screen_resolution` VARCHAR(20)  DEFAULT NULL,
    `language`          VARCHAR(20)  DEFAULT NULL,
    `ip_address`        VARCHAR(45)  DEFAULT NULL,
    `user_agent`        TEXT         DEFAULT NULL,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_ua_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблиця: settings
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`   VARCHAR(50) NOT NULL UNIQUE,
    `value` VARCHAR(255) NOT NULL DEFAULT '',
    INDEX `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблиця: score_adjustments
-- ----------------------------
CREATE TABLE IF NOT EXISTS `score_adjustments` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `team`       TINYINT UNSIGNED NOT NULL COMMENT '1 або 2',
    `points`     INT NOT NULL COMMENT 'Додатні = додати, від''ємні = відняти',
    `reason`     VARCHAR(255) NOT NULL DEFAULT '',
    `admin_id`   INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_team` (`team`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- ПОЧАТКОВІ ДАНІ
-- =============================================================

-- Адмін (логін: admin, пароль: admin123)
-- Хеш згенерований: php -r "echo password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>10]);"
INSERT INTO `users` (`username`, `password_hash`, `team`, `role`) VALUES
('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 1, 'admin');

-- Ініціалізація стану гри (один рядок з id=1)
INSERT INTO `game_state` (`id`, `current_question_id`, `is_button_locked`, `is_game_active`)
VALUES (1, NULL, 0, 0);

-- Початкові налаштування
INSERT INTO `settings` (`key`, `value`) VALUES
('team1_name', 'Команда 1'),
('team2_name', 'Команда 2'),
('show_options', '1');

-- =============================================================
-- 15 запитань про безпеку в Інтернеті (українською)
-- =============================================================

-- 1
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що таке фішинг (phishing)?',
 '["Вид комп\'ютерного вірусу", "Метод шахрайства для викрадення даних через підроблені сайти", "Програма для захисту комп\'ютера", "Спосіб прискорення Інтернету"]',
 1, 10, 1, 1);

-- 2
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Який пароль найбезпечніший?',
 '["123456", "password", "Kf$9mP!2xLq#", "qwerty123"]',
 2, 10, 1, 2);

-- 3
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Які дані НЕ можна публікувати в соцмережах?',
 '["Улюблений фільм", "Домашню адресу та номер телефону", "Фото з подорожі", "Назву улюбленої книги"]',
 1, 10, 1, 3);

-- 4
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що робити при кібербулінгу?',
 '["Відповісти образами", "Зберегти докази, заблокувати агресора, повідомити дорослих", "Видалити акаунт", "Ігнорувати і нікому не казати"]',
 1, 10, 1, 4);

-- 5
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що означає замок в адресному рядку браузера?',
 '["Сайт заблоковано", "З\'єднання зашифроване (HTTPS)", "Сайт є державним", "Потрібен пароль для входу"]',
 1, 10, 1, 5);

-- 6
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Як розпізнати фейкову новину?',
 '["Вона має яскравий заголовок", "Перевірити джерело та підтвердження в інших ЗМІ", "Вона є в соціальній мережі", "Під нею багато коментарів"]',
 1, 10, 1, 6);

-- 7
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Чому небезпечний відкритий Wi-Fi?',
 '["Інтернет там повільний", "Зловмисники можуть перехопити ваші дані", "Це дорого коштує", "Батарея швидше розряджається"]',
 1, 10, 1, 7);

-- 8
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що таке двофакторна аутентифікація (2FA)?',
 '["Два різних паролі", "Підтвердження входу двома способами: пароль + код з телефону", "Два акаунти одночасно", "Подвійне шифрування файлів"]',
 1, 10, 1, 8);

-- 9
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Як найчастіше заражають комп\'ютер вірусами?',
 '["Перегляд погоди онлайн", "Відкриття підозрілих вкладень в електронних листах", "Використання калькулятора", "Оновлення операційної системи"]',
 1, 10, 1, 9);

-- 10
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що таке файли cookies?',
 '["Віруси", "Невеликі файли з інформацією про відвідування сайтів", "Зображення на сайті", "Рекламні банери"]',
 1, 10, 1, 10);

-- 11
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що таке соціальна інженерія в кібербезпеці?',
 '["Розробка соціальних мереж", "Маніпулювання людьми для отримання конфіденційної інформації", "Побудова мостів та доріг", "Навчання програмуванню"]',
 1, 10, 1, 11);

-- 12
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Для чого використовується VPN?',
 '["Для прискорення Інтернету", "Для створення захищеного з\'єднання та анонімності", "Для блокування реклами", "Для збільшення пам\'яті"]',
 1, 10, 1, 12);

-- 13
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що таке цифровий слід?',
 '["Відбиток пальця на екрані", "Інформація, яку ви залишаєте в Інтернеті своєю активністю", "Тип комп\'ютерного вірусу", "Цифрова фотографія"]',
 1, 15, 1, 13);

-- 14
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Навіщо регулярно оновлювати програмне забезпечення?',
 '["Щоб мати нові шпалери", "Оновлення закривають вразливості безпеки та виправляють помилки", "Це не важливо, можна не оновлювати", "Щоб телефон працював повільніше"]',
 1, 10, 1, 14);

-- 15
INSERT INTO `questions` (`question_text`, `options`, `correct_answer`, `points`, `is_active`, `sort_order`) VALUES
('Що таке право на приватність в Інтернеті?',
 '["Право мати приватний сервер", "Право контролювати свої персональні дані та їхнє використання", "Право не платити за Інтернет", "Право видаляти чужі пости"]',
 1, 15, 1, 15);