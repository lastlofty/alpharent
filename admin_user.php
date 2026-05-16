<?php
require __DIR__ . '/includes/db.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$id]);
$u = $st->fetch();
if (!$u) {
    header('Location: admin.php');
    exit;
}

$tariffs = billing_tariffs();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Сессия устарела, обновите страницу.';
    } else {
        $action  = $_POST['action'] ?? '';
        $amount  = (int)($_POST['amount'] ?? 0);
        $days    = (int)($_POST['days'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($action === 'set_tariff') {
            $model = trim($_POST['model'] ?? '');
            if (isset($tariffs[$model])) {
                db()->prepare('UPDATE users SET tariff_model = ?, weekly_price = ? WHERE id = ?')
                    ->execute([$model, $tariffs[$model], $id]);
                $msg = 'Тариф сохранён: ' . $model . ' — ' . money($tariffs[$model]) . '/нед.';
            } else {
                $err = 'Выберите модель из списка.';
            }
        } elseif ($action === 'start') {
            if ((int)$u['weekly_price'] <= 0 || !$u['tariff_model']) {
                $err = 'Сначала назначьте тариф.';
            } else {
                $startDate = $u['rental_start'] ?: date('Y-m-d H:i:s');
                $next = date('Y-m-d H:i:s', strtotime('+7 days'));
                db()->prepare('UPDATE users SET rental_active = 1, rental_start = ?, next_charge_at = ? WHERE id = ?')
                    ->execute([$startDate, $next, $id]);
                billing_log_add($id, $u['rental_start'] ? 'resume' : 'start', 0, 0,
                    'Тариф: ' . $u['tariff_model'] . ', ' . money($u['weekly_price']) . '/нед', 'админ');
                $msg = 'Аренда запущена. Первое начисление — через неделю.';
            }
        } elseif ($action === 'pause') {
            db()->prepare('UPDATE users SET rental_active = 0 WHERE id = ?')->execute([$id]);
            billing_log_add($id, 'pause', 0, 0, 'Начисления приостановлены', 'админ');
            $msg = 'Аренда приостановлена — начисления больше не идут.';
        } elseif ($action === 'payment') {
            if ($amount > 0) {
                $newDebt = max(0, (int)$u['debt'] - $amount);
                db()->prepare('UPDATE users SET debt = ? WHERE id = ?')->execute([$newDebt, $id]);
                billing_log_add($id, 'payment', $amount, 0, $comment !== '' ? $comment : 'Оплата принята', 'админ');
                $msg = 'Оплата отмечена: ' . money($amount) . '.';
            } else {
                $err = 'Введите сумму больше нуля.';
            }
        } elseif ($action === 'fine') {
            if ($amount > 0) {
                db()->prepare('UPDATE users SET debt = debt + ? WHERE id = ?')->execute([$amount, $id]);
                billing_log_add($id, 'fine', $amount, 0, $comment !== '' ? $comment : 'Штраф', 'админ');
                $msg = 'Штраф добавлен: ' . money($amount) . '.';
            } else {
                $err = 'Введите сумму штрафа больше нуля.';
            }
        } elseif ($action === 'adjust') {
            if ($amount !== 0) {
                $newDebt = max(0, (int)$u['debt'] + $amount);
                db()->prepare('UPDATE users SET debt = ? WHERE id = ?')->execute([$newDebt, $id]);
                billing_log_add($id, 'adjust', $amount, 0,
                    $comment !== '' ? $comment : 'Ручная корректировка долга', 'админ');
                $msg = 'Долг скорректирован на ' . ($amount > 0 ? '+' : '') . money($amount) . '.';
            } else {
                $err = 'Введите сумму корректировки (можно отрицательную).';
            }
        } elseif ($action === 'free_days') {
            if ($days > 0) {
                db()->prepare('UPDATE users SET free_days = free_days + ? WHERE id = ?')->execute([$days, $id]);
                billing_log_add($id, 'free_days', 0, $days, $comment !== '' ? $comment : 'Бесплатные дни', 'админ');
                $msg = 'Начислено бесплатных дней: ' . $days . '.';
            } else {
                $err = 'Введите количество дней больше нуля.';
            }
        }
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();
    }
}

$h = db()->prepare('SELECT * FROM billing_log WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$h->execute([$id]);
$history = $h->fetchAll();

$PAGE_TITLE = 'Управление: ' . $u['name'] . ' — Alpha Rent';
$ACTIVE = '';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container account-wrap" style="max-width:760px">

    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px">
      <h1 style="font-size:26px">Управление клиентом</h1>
      <a href="admin.php" class="btn btn-outline">← К списку</a>
    </div>

    <?php if ($msg): ?><div class="alert alert--ok"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert--error"><?= e($err) ?></div><?php endif; ?>

    <div class="account-box">
      <h2>Клиент</h2>
      <div class="data-row"><span>Имя</span><b><?= e($u['name']) ?></b></div>
      <div class="data-row"><span>Телефон</span><b><?= e($u['phone']) ?></b></div>
      <div class="data-row"><span>E-mail</span><b><?= e($u['email']) ?></b></div>
      <div class="data-row"><span>Модель</span><b><?= $u['tariff_model'] ? e($u['tariff_model']) : '— не назначена —' ?></b></div>
      <div class="data-row"><span>Цена недели</span><b><?= (int)$u['weekly_price'] > 0 ? money($u['weekly_price']) : '—' ?></b></div>
      <div class="data-row"><span>Бесплатные дни</span><b><?= (int)$u['free_days'] ?></b></div>
      <div class="data-row"><span>Аренда</span><b><?= ((int)$u['rental_active'] === 1) ? 'активна' : 'приостановлена' ?></b></div>
      <div class="data-row"><span>Долг</span><b style="color:<?= (int)$u['debt'] > 0 ? 'var(--red)' : 'inherit' ?>"><?= money($u['debt']) ?></b></div>
    </div>

    <div class="account-box">
      <h2>Тариф и аренда</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="set_tariff">
        <div class="field">
          <label for="model">Модель электровелосипеда</label>
          <select id="model" name="model">
            <?php foreach ($tariffs as $m => $price): ?>
              <option value="<?= e($m) ?>" <?= $u['tariff_model'] === $m ? 'selected' : '' ?>>
                <?= e($m) ?> — <?= money($price) ?>/нед
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить тариф</button>
      </form>
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <?php if ((int)$u['rental_active'] === 1): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="pause">
            <button type="submit" class="btn btn-outline">Приостановить аренду</button>
          </form>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="btn btn-primary">Запустить аренду</button>
          </form>
        <?php endif; ?>
      </div>
      <p class="form__note">При запуске первое начисление произойдёт через 7 дней. Пауза останавливает начисления (например, клиент вернул технику).</p>
    </div>

    <div class="account-box">
      <h2>Оплата и долг</h2>
      <div class="grid grid-2" style="gap:16px">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="payment">
          <div class="field">
            <label>Отметить оплату, ₽</label>
            <input type="number" name="amount" min="1" placeholder="Сумма">
          </div>
          <div class="field">
            <label>Комментарий</label>
            <input type="text" name="comment" placeholder="Напр.: оплата через Точку">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Зачесть оплату</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="fine">
          <div class="field">
            <label>Штраф, ₽</label>
            <input type="number" name="amount" min="1" placeholder="Сумма">
          </div>
          <div class="field">
            <label>За что</label>
            <input type="text" name="comment" placeholder="Напр.: повреждение техники">
          </div>
          <button type="submit" class="btn btn-outline btn-block">Выставить штраф</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="adjust">
          <div class="field">
            <label>Корректировка долга, ₽</label>
            <input type="number" name="amount" placeholder="+ увеличить, − уменьшить">
          </div>
          <div class="field">
            <label>Причина</label>
            <input type="text" name="comment" placeholder="Комментарий">
          </div>
          <button type="submit" class="btn btn-outline btn-block">Изменить долг</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="free_days">
          <div class="field">
            <label>Бесплатные дни</label>
            <input type="number" name="days" min="1" placeholder="Кол-во дней">
          </div>
          <div class="field">
            <label>Причина</label>
            <input type="text" name="comment" placeholder="Напр.: привёл друга">
          </div>
          <button type="submit" class="btn btn-outline btn-block">Начислить дни</button>
        </form>
      </div>
    </div>

    <div class="account-box">
      <h2>История операций</h2>
      <?php if (!$history): ?>
        <p style="color:var(--muted)">Операций пока нет.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="price-table">
            <thead><tr><th>Дата</th><th>Операция</th><th>Сумма / дни</th><th>Комментарий</th><th>Кто</th></tr></thead>
            <tbody>
              <?php foreach ($history as $r): ?>
                <tr>
                  <td><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                  <td><?= e(billing_type_label($r['type'])) ?></td>
                  <td>
                    <?php if ($r['type'] === 'free_days' && (int)$r['days'] !== 0): ?>
                      <?= (int)$r['days'] > 0 ? '+' : '−' ?><?= abs((int)$r['days']) ?> дн.
                    <?php elseif ((int)$r['amount'] !== 0): ?>
                      <?php $minus = $r['type'] === 'payment' || (int)$r['amount'] < 0; ?>
                      <?= $minus ? '−' : '+' ?><?= money(abs((int)$r['amount'])) ?>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td><?= e($r['comment']) ?></td>
                  <td><?= e($r['created_by']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
