<?php
/* Подключение к базе, сессия и общие функции личного кабинета */

require_once __DIR__ . '/../config.php';

// Адрес сайта и адрес отправителя писем (можно переопределить в config.php)
if (!defined('SITE_URL'))  { define('SITE_URL', 'https://alpha-rent.ru'); }
if (!defined('MAIL_FROM')) { define('MAIL_FROM', 'Alpha Rent <noreply@alpha-rent.ru>'); }

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
                is_verified   TINYINT      NOT NULL DEFAULT 0,
                verify_token  VARCHAR(64)  NULL,
                created_at    DATETIME     NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        // Миграция: дозаписываем недостающие столбцы в уже существующую таблицу
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_verified TINYINT NOT NULL DEFAULT 0');
            $pdo->exec('UPDATE users SET is_verified = 1'); // прежних пользователей считаем подтверждёнными
        } catch (PDOException $e) { /* столбец уже существует */ }
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN verify_token VARCHAR(64) NULL');
        } catch (PDOException $e) { /* столбец уже существует */ }
        // Миграция: столбцы биллинга (аренда и оплата)
        $billingCols = [
            'ALTER TABLE users ADD COLUMN tariff_model VARCHAR(80) NULL',
            'ALTER TABLE users ADD COLUMN weekly_price INT NOT NULL DEFAULT 0',
            'ALTER TABLE users ADD COLUMN rental_active TINYINT NOT NULL DEFAULT 0',
            'ALTER TABLE users ADD COLUMN rental_start DATETIME NULL',
            'ALTER TABLE users ADD COLUMN next_charge_at DATETIME NULL',
            'ALTER TABLE users ADD COLUMN free_days INT NOT NULL DEFAULT 0',
            'ALTER TABLE users ADD COLUMN debt INT NOT NULL DEFAULT 0',
        ];
        foreach ($billingCols as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* столбец уже существует */ }
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS billing_log (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NOT NULL,
                type       VARCHAR(20) NOT NULL,
                amount     INT NOT NULL DEFAULT 0,
                days       INT NOT NULL DEFAULT 0,
                comment    VARCHAR(255) NULL,
                created_by VARCHAR(40) NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
    return $pdo;
}

/* Экранирование вывода */
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* Проверка российского номера телефона. Возвращает 11 цифр или false. */
function valid_ru_phone($raw) {
    $d = preg_replace('/\D/', '', (string)$raw);
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    return (strlen($d) === 11 && $d[0] === '7') ? $d : false;
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

/* Отправка письма со ссылкой подтверждения e-mail */
function send_verification_email($email, $name, $token) {
    $link = SITE_URL . '/verify.php?token=' . urlencode($token);
    $subject = '=?UTF-8?B?' . base64_encode('Подтверждение регистрации — Alpha Rent') . '?=';
    $body = 'Здравствуйте, ' . $name . "!\r\n\r\n"
          . "Вы зарегистрировались на сайте Alpha Rent.\r\n"
          . "Подтвердите ваш e-mail — перейдите по ссылке:\r\n\r\n"
          . $link . "\r\n\r\n"
          . "Если вы не регистрировались, просто проигнорируйте это письмо.\r\n\r\n"
          . "Alpha Rent — аренда электровелосипедов в Казани\r\n"
          . SITE_URL . "\r\n";
    $headers = 'From: ' . MAIL_FROM . "\r\n"
             . 'Reply-To: ' . MAIL_FROM . "\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n";
    return @mail($email, $subject, $body, $headers);
}

/* Отправка сообщения в Telegram-бота. Возвращает true при успехе. */
function send_telegram($text) {
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
        return false;
    }
    $token = (string)TELEGRAM_BOT_TOKEN;
    $chat  = (string)TELEGRAM_CHAT_ID;
    if ($token === '' || $chat === '' || mb_strpos($token, 'ВПИШИТЕ') !== false) {
        return false; // бот ещё не настроен
    }
    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $params = [
        'chat_id' => $chat,
        'text'    => $text,
        'disable_web_page_preview' => 'true',
    ];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($params),
        'timeout' => 10,
    ]]);
    return @file_get_contents($url, false, $ctx) !== false;
}

/* Тарифы аренды: модель => цена за неделю (₽) */
function billing_tariffs() {
    return [
        'Truck+'                        => 2500,
        'Jetson Monster'                => 3000,
        'Kugoo V3 Pro'                  => 3000,
        'Saige Monster'                 => 3000,
        'Saige GT1 (1×50 Ah + 2×30 Ah)' => 4000,
        'Saige GT1 (2×50 Ah)'           => 4500,
    ];
}

/* Запись операции в историю биллинга */
function billing_log_add($userId, $type, $amount, $days, $comment, $by) {
    db()->prepare(
        'INSERT INTO billing_log (user_id, type, amount, days, comment, created_by, created_at)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([(int)$userId, $type, (int)$amount, (int)$days, $comment, $by, date('Y-m-d H:i:s')]);
}

/* Человеческое название операции биллинга */
function billing_type_label($type) {
    $map = [
        'charge'    => 'Начисление за неделю',
        'payment'   => 'Оплата',
        'fine'      => 'Штраф',
        'free_days' => 'Бесплатные дни',
        'adjust'    => 'Корректировка долга',
        'start'     => 'Старт аренды',
        'pause'     => 'Аренда приостановлена',
        'resume'    => 'Аренда возобновлена',
    ];
    return $map[$type] ?? $type;
}

/* Сумма в рублях для вывода */
function money($n) {
    return number_format((int)$n, 0, '', "\xC2\xA0") . "\xC2\xA0\xE2\x82\xBD";
}

/* Проверка прав администратора */
function is_admin() {
    return !empty($_SESSION['admin']);
}
function require_admin() {
    if (!is_admin()) {
        header('Location: admin.php');
        exit;
    }
}

/* Дозапись заявки в файл requests.csv (открывается в Excel) */
function append_request_csv($name, $phone, $data) {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
    $file = $dir . '/requests.csv';
    $isNew = !file_exists($file);
    $fh = @fopen($file, 'a');
    if (!$fh) {
        return;
    }
    if ($isNew) {
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['Дата', 'Имя', 'Телефон', 'Модель', 'Срок', 'Тип техники', 'Тема', 'Комментарий', 'Страница'], ';');
    }
    fputcsv($fh, [
        date('Y-m-d H:i:s'), $name, $phone,
        trim($data['model'] ?? ''),
        trim($data['term'] ?? ''),
        trim($data['type'] ?? ''),
        trim($data['topic'] ?? ''),
        trim($data['comment'] ?? ''),
        trim($data['source'] ?? ''),
    ], ';');
    fclose($fh);
}
