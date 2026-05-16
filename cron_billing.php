<?php
/*
  Еженедельное начисление платы за аренду.
  Запускается планировщиком (CronTab) раз в сутки.
  Начисление происходит только тем, у кого подошёл срок (next_charge_at).
*/
require __DIR__ . '/includes/db.php';

// Доступ: из консоли сервера или по секретному ключу ?key=...
if (php_sapi_name() !== 'cli') {
    $key = (string)($_GET['key'] ?? '');
    if (!defined('CRON_SECRET') || (string)CRON_SECRET === ''
        || mb_strpos((string)CRON_SECRET, 'ВПИШИТЕ') !== false
        || !hash_equals((string)CRON_SECRET, $key)) {
        http_response_code(403);
        exit('Доступ запрещён');
    }
}

$st = db()->prepare(
    'SELECT * FROM users
     WHERE rental_active = 1 AND next_charge_at IS NOT NULL AND next_charge_at <= ?'
);
$st->execute([date('Y-m-d H:i:s')]);
$users = $st->fetchAll();

$count = 0;
$totalCharged = 0;

foreach ($users as $u) {
    $debt  = (int)$u['debt'];
    $free  = (int)$u['free_days'];
    $price = (int)$u['weekly_price'];
    $next  = strtotime($u['next_charge_at']);
    $guard = 0;

    while ($next <= time() && $guard < 60) {
        $guard++;
        $freeUsed = min($free, 7);
        $charge   = (int)round($price * (7 - $freeUsed) / 7);
        $debt    += $charge;
        $free    -= $freeUsed;
        $next     = strtotime('+7 days', $next);

        $comment = 'Начисление за неделю аренды';
        if ($freeUsed > 0) {
            $comment .= ' (бесплатных дней зачтено: ' . $freeUsed . ')';
        }
        billing_log_add($u['id'], 'charge', $charge, $freeUsed, $comment, 'система');
        $totalCharged += $charge;
    }

    db()->prepare('UPDATE users SET debt = ?, free_days = ?, next_charge_at = ? WHERE id = ?')
        ->execute([$debt, $free, date('Y-m-d H:i:s', $next), $u['id']]);
    $count++;
}

echo 'Начисление выполнено ' . date('Y-m-d H:i') . '. '
   . 'Пользователей обработано: ' . $count . ', '
   . 'начислено всего: ' . $totalCharged . ' руб.' . "\n";
