<?php
/* Приём заявок с сайта и пересылка в Telegram-бот */
require __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

// Ловушка для спам-ботов: скрытое поле, которое заполняют только боты
if (trim($_POST['website'] ?? '') !== '') {
    echo json_encode(['ok' => true]); // тихо игнорируем
    exit;
}

$name   = trim($_POST['name'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$source = trim($_POST['source'] ?? '');

if (mb_strlen($name) < 2 || !valid_ru_phone($phone)) {
    echo json_encode(['ok' => false, 'error' => 'validation']);
    exit;
}

// Сохраняем заявку в файл — на случай, если Telegram недоступен
append_request_csv($name, $phone, $_POST);

// Формируем сообщение для Telegram
$pay    = trim($_POST['pay'] ?? '');
$isCard = ($pay !== '' && mb_stripos($pay, 'карт') !== false);

$fields = [
    'model'   => 'Модель',
    'term'    => 'Срок аренды',
    'type'    => 'Тип техники',
    'topic'   => 'Тема обращения',
    'comment' => 'Комментарий',
];
$lines = [];
$lines[] = '🚲 Новая заявка — Alpha Rent';
$lines[] = '';
$lines[] = 'Имя: ' . $name;
$lines[] = 'Телефон: ' . $phone;
foreach ($fields as $key => $label) {
    $val = trim($_POST[$key] ?? '');
    if ($val !== '') {
        $lines[] = $label . ': ' . $val;
    }
}
if ($pay !== '') {
    $lines[] = $isCard
        ? '💳 Оплата: онлайн картой — проверьте поступление в Точке'
        : '💵 Оплата: наличными при получении';
}
if ($source !== '') {
    $lines[] = 'Страница: ' . $source;
}
$lines[] = 'Время: ' . date('d.m.Y H:i');

// Сохраняем заявку в базу — для раздела «Заявки» в админ-панели
$detailParts = [];
foreach ($fields as $key => $label) {
    $v = trim($_POST[$key] ?? '');
    if ($v !== '') { $detailParts[] = $label . ': ' . $v; }
}
if ($pay !== '') { $detailParts[] = 'Оплата: ' . ($isCard ? 'картой онлайн' : 'наличными'); }
db()->prepare('INSERT INTO requests (name, phone, details, source, created_at) VALUES (?,?,?,?,?)')
    ->execute([$name, $phone, mb_substr(implode('; ', $detailParts), 0, 500), mb_substr($source, 0, 160), date('Y-m-d H:i:s')]);

send_telegram(implode("\n", $lines));

// Если выбрана оплата картой — возвращаем ссылку для перехода к оплате
$payUrl = '';
if ($isCard && defined('PAYMENT_LINK') && (string)PAYMENT_LINK !== ''
    && mb_strpos((string)PAYMENT_LINK, 'ВПИШИТЕ') === false) {
    $payUrl = PAYMENT_LINK;
}

echo json_encode(['ok' => true, 'pay_url' => $payUrl]);
