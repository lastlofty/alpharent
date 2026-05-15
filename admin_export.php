<?php
require __DIR__ . '/includes/db.php';

if (empty($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

$rows = db()->query('SELECT id, name, phone, email, is_verified, created_at FROM users ORDER BY id')->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="alpha-rent-users.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM — корректная кириллица в Excel
fputcsv($out, ['ID', 'Имя', 'Телефон', 'E-mail', 'Статус', 'Дата регистрации'], ';');
foreach ($rows as $r) {
    $status = ((int)$r['is_verified'] === 1) ? 'Подтверждён' : 'Не подтверждён';
    fputcsv($out, [$r['id'], $r['name'], $r['phone'], $r['email'], $status, $r['created_at']], ';');
}
fclose($out);
exit;
