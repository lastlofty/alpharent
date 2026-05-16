<?php
require __DIR__ . '/includes/db.php';
require_admin();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Сессия устарела, обновите страницу.';
    } else {
        $action = $_POST['action'] ?? '';
        $bid = (int)($_POST['booking_id'] ?? 0);
        $bk = null;
        if ($bid) {
            $s = db()->prepare('SELECT * FROM bookings WHERE id = ?');
            $s->execute([$bid]);
            $bk = $s->fetch();
        }
        if (!$bk) {
            $err = 'Бронь не найдена.';
        } elseif ($action === 'assign') {
            $bikeId = (int)($_POST['bike_id'] ?? 0);
            $s = db()->prepare('SELECT * FROM bikes WHERE id = ?');
            $s->execute([$bikeId]);
            $bike = $s->fetch();
            if (!$bike) {
                $err = 'Выберите велосипед.';
            } elseif ($bike['status'] !== 'free') {
                $err = 'Этот велосипед уже занят — обновите страницу.';
            } elseif ($bike['model'] !== $bk['model']) {
                $err = 'Выбран велосипед другой модели.';
            } else {
                db()->prepare('UPDATE bookings SET bike_id = ?, status = "confirmed" WHERE id = ?')->execute([$bikeId, $bid]);
                db()->prepare('UPDATE bikes SET status = "booked" WHERE id = ?')->execute([$bikeId]);
                $msg = 'Бронь подтверждена, велосипед №' . (int)$bike['number'] . ' закреплён за клиентом.';
            }
        } elseif ($action === 'pickup') {
            if ($bk['status'] === 'confirmed') {
                db()->prepare('UPDATE bookings SET status = "active" WHERE id = ?')->execute([$bid]);
                if ($bk['bike_id']) {
                    db()->prepare('UPDATE bikes SET status = "rented" WHERE id = ?')->execute([$bk['bike_id']]);
                }
                if (!empty($_POST['burn'])) {
                    db()->prepare('UPDATE users SET free_days = GREATEST(0, free_days - 1) WHERE id = ?')->execute([$bk['user_id']]);
                    billing_log_add($bk['user_id'], 'free_days', 0, -1,
                        'Бонусный день сгорел — клиент не явился в назначенный день', 'админ');
                }
                $msg = 'Отмечена выдача велосипеда клиенту.';
            } else {
                $err = 'Выдать можно только подтверждённую бронь.';
            }
        } elseif ($action === 'finish') {
            db()->prepare('UPDATE bookings SET status = "done" WHERE id = ?')->execute([$bid]);
            if ($bk['bike_id']) {
                db()->prepare('UPDATE bikes SET status = "free" WHERE id = ?')->execute([$bk['bike_id']]);
            }
            $msg = 'Бронь завершена, велосипед снова свободен.';
        } elseif ($action === 'cancel') {
            db()->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ?')->execute([$bid]);
            if ($bk['bike_id']) {
                db()->prepare('UPDATE bikes SET status = "free" WHERE id = ?')->execute([$bk['bike_id']]);
            }
            $msg = 'Бронь отменена.';
        }
    }
}

$bookings = db()->query(
    'SELECT b.*, u.name AS uname, u.phone AS uphone, bk.number AS bike_number
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     LEFT JOIN bikes bk ON bk.id = b.bike_id
     ORDER BY b.id DESC'
)->fetchAll();

$freeBikes = [];
foreach (db()->query("SELECT id, model, number FROM bikes WHERE status = 'free' ORDER BY model, number")->fetchAll() as $fb) {
    $freeBikes[$fb['model']][] = $fb;
}

$PAGE_TITLE = 'Брони — Alpha Rent';
$ACTIVE = '';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container account-wrap" style="max-width:820px">

    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px">
      <h1 style="font-size:28px">Брони</h1>
      <a href="admin.php" class="btn btn-outline">← В админ-панель</a>
    </div>

    <?php if ($msg): ?><div class="alert alert--ok"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert--error"><?= e($err) ?></div><?php endif; ?>

    <?php if (!$bookings): ?>
      <div class="alert alert--ok">Заявок на бронь пока нет.</div>
    <?php else: ?>
      <?php foreach ($bookings as $b): ?>
        <?php $st = $b['status']; ?>
        <div class="account-box">
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:baseline">
            <h2 style="margin-bottom:0">Бронь #<?= (int)$b['id'] ?> — <?= e($b['model']) ?></h2>
            <span class="badge"><?= e(booking_status_label($st)) ?></span>
          </div>
          <div style="margin:14px 0">
            <div class="data-row"><span>Клиент</span><b><?= e($b['uname']) ?></b></div>
            <div class="data-row"><span>Телефон</span><b><?= e($b['uphone']) ?></b></div>
            <div class="data-row"><span>Дата начала</span><b><?= e(date('d.m.Y', strtotime($b['start_date']))) ?></b></div>
            <div class="data-row"><span>Срок</span><b><?= (int)$b['weeks'] ?> нед.</b></div>
            <div class="data-row"><span>Велосипед</span><b><?= $b['bike_number'] ? '№ ' . (int)$b['bike_number'] : 'не назначен' ?></b></div>
            <?php if ($b['comment']): ?>
              <div class="data-row"><span>Комментарий</span><b><?= e($b['comment']) ?></b></div>
            <?php endif; ?>
          </div>

          <?php if ($st === 'new'): ?>
            <?php $fb = $freeBikes[$b['model']] ?? []; ?>
            <?php if (!$fb): ?>
              <div class="alert alert--error">Нет свободных велосипедов модели «<?= e($b['model']) ?>». Освободите технику в разделе «Парк техники».</div>
            <?php else: ?>
              <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <div class="field" style="margin-bottom:0;min-width:200px">
                  <label>Свободный велосипед</label>
                  <select name="bike_id">
                    <?php foreach ($fb as $bike): ?>
                      <option value="<?= (int)$bike['id'] ?>">№ <?= (int)$bike['number'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-primary">Подтвердить и закрепить</button>
              </form>
            <?php endif; ?>
            <form method="post" style="margin-top:10px" onsubmit="return confirm('Отменить бронь?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="btn btn-outline">Отменить бронь</button>
            </form>

          <?php elseif ($st === 'confirmed'): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="pickup">
              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
              <label style="display:flex;gap:8px;align-items:center;font-size:14px;color:var(--muted);margin-bottom:12px">
                <input type="checkbox" name="burn" value="1" style="width:auto">
                Клиент явился не в назначенный день — сжечь 1 бонусный день
              </label>
              <button type="submit" class="btn btn-primary">Отметить выдачу велосипеда</button>
            </form>
            <form method="post" style="margin-top:10px" onsubmit="return confirm('Отменить бронь?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="btn btn-outline">Отменить бронь</button>
            </form>

          <?php elseif ($st === 'active'): ?>
            <form method="post" onsubmit="return confirm('Завершить бронь? Велосипед станет свободным.');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="finish">
              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="btn btn-primary">Завершить бронь</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
