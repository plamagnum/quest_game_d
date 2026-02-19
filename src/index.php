<?php
/**
 * Головний роутер додатку
 *
 * API-запити (/api/*.php) обробляються Nginx напряму через FastCGI.
 * Усі інші маршрути — перенаправляються на відповідні views.
 *
 * Маршрути:
 *   /login   — сторінка входу/реєстрації
 *   /quest   — сторінка квесту (для учнів)
 *   /admin   — адмін-панель
 *   /results — таблиця результатів
 *   /        — редірект на /quest або /login
 *
 * @package QuestGame
 */

declare(strict_types=1);

session_start();

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$page       = trim($requestUri, '/');

// За замовчуванням — сторінка квесту
if ($page === '') {
    $page = 'quest';
}

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = $isLoggedIn && ($_SESSION['role'] ?? '') === 'admin';

switch ($page) {
    case 'login':
        // Якщо вже авторизований — редірект
        if ($isLoggedIn) {
            header('Location: ' . ($isAdmin ? '/admin' : '/quest'));
            exit;
        }
        include __DIR__ . '/views/login.php';
        break;

    case 'quest':
        // Тільки авторизовані, не адміни
        if (!$isLoggedIn) {
            header('Location: /login');
            exit;
        }
        if ($isAdmin) {
            header('Location: /admin');
            exit;
        }
        include __DIR__ . '/views/quest.php';
        break;

    case 'admin':
        // Тільки адміни
        if (!$isLoggedIn) {
            header('Location: /login');
            exit;
        }
        if (!$isAdmin) {
            header('Location: /quest');
            exit;
        }
        include __DIR__ . '/views/admin.php';
        break;

    case 'results':
        // Тільки авторизовані
        if (!$isLoggedIn) {
            header('Location: /login');
            exit;
        }
        include __DIR__ . '/views/results.php';
        break;

    default:
        // Невідомий маршрут — редірект
        header('Location: ' . ($isLoggedIn ? '/quest' : '/login'));
        exit;
}