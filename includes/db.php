<?php
/* Подключение к базе, сессия и общие функции личного кабинета */

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Подключение к базе данных (PDO). При первом вызове создаёт таблицу users. */
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('Не удаётся подключиться к базе данных. Проверьте настройки в файле config.php.');
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                name          VARCHAR(120) NOT NULL,
                phone         VARCHAR(40)  NOT NULL,
                email         VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at    DATETIME     NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
    return $pdo;
}

/* Экранирование вывода */
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* Защита форм от подделки запросов (CSRF) */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_check() {
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* Текущий пользователь или null */
function current_user() {
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    if (empty($_SESSION['uid'])) {
        $cached = null;
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $cached = $st->fetch() ?: null;
    return $cached;
}

/* Доступ только для авторизованных */
function require_login() {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

/* Дозапись зарегистрированного пользователя в файл users.csv (открывается в Excel) */
function append_user_csv($name, $phone, $email) {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
    $file = $dir . '/users.csv';
    $isNew = !file_exists($file);
    $fh = @fopen($file, 'a');
    if (!$fh) {
        return;
    }
    if ($isNew) {
        fwrite($fh, "\xEF\xBB\xBF"); // BOM — чтобы кириллица корректно открывалась в Excel
        fputcsv($fh, ['Имя', 'Телефон', 'E-mail', 'Дата регистрации'], ';');
    }
    fputcsv($fh, [$name, $phone, $email, date('Y-m-d H:i:s')], ';');
    fclose($fh);
}
