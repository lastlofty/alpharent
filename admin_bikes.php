<?php
require __DIR__ . '/includes/db.php';
require_admin();

$models   = bike_models();
$statuses = bike_statuses();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Сессия устарела, обновите страницу.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $model  = trim($_POST['model'] ?? '');
            $from   = (int)($_POST['from'] ?? 0);
            $toRaw  = trim($_POST['to'] ?? '');
            $to     = ($toRaw === '') ? $from : (int)$toRaw;
            $status = $_POST['status'] ?? 'free';
            if (!in_array($model, $models, true)) {
                $err = 'Выберите модель из списка.';
            } elseif (!isset($statuses[$status])) {
                $err = 'Неверный статус.';
            } elseif ($from < 1 || $to < $from) {
                $err = 'Проверьте номера: «от» должно быть не больше «до».';
            } elseif ($to - $from > 300) {
                $err = 'За один раз можно добавить не более 300 велосипедов.';
            } else {
                $ex = db()->prepare('SELECT number FROM bikes WHERE model = ?');
                $ex->execute([$model]);
                $exist = [];
                foreach ($ex->fetchAll() as $r) { $exist[(int)$r['number']] = true; }
                $ins = db()->prepare('INSERT INTO bikes (model, number, status, created_at) VALUES (?,?,?,?)');
                $added = 0; $skipped = 0;
                for ($n = $from; $n <= $to; $n++) {
                    if (isset($exist[$n])) { $skipped++; continue; }
                    $ins->execute([$model, $n, $status, date('Y-m-d H:i:s')]);
                    $added++;
                }
                $msg = 'Добавлено велосипедов: ' . $added
                     . ($skipped ? '. Пропущено (такие номера уже есть): ' . $skipped : '') . '.';
            }
        } elseif ($action === 'status') {
            $bid    = (int)($_POST['bike_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (isset($statuses[$status])) {
                db()->prepare('UPDATE bikes SET status = ? WHERE id = ?')->execute([$status, $bid]);
                $msg = 'Статус обновлён.';
            }
        } elseif ($action === 'delete') {
            $bid = (int)($_POST['bike_id'] ?? 0);
            db()->prepare('DELETE FROM bikes WHERE id = ?')->execute([$bid]);
            $msg = 'Велосипед удалён.';
        }
    }
}

$filter = $_GET['model'] ?? '';
if ($filter !== '' && in_array($filter, $models, true)) {
    $st = db()->prepare('SELECT * FROM bikes WHERE model = ? ORDER BY number');
    $st->execute([$filter]);
    $bikes = $st->fetchAll();
} else {
    $filter = '';
    $bikes = db()->query('SELECT * FROM bikes ORDER BY model, number')->fetchAll();
}

$stats = ['total' => 0];
foreach (array_keys($statuses) as $k) { $stats[$k] = 0; }
foreach (db()->query('SELECT status, COUNT(*) c FROM bikes GROUP BY status')->fetchAll() as $r) {
    $stats[$r['status']] = (int)$r['c'];
    $stats['total'] += (int)$r['c'];
}

$PAGE_TITLE = 'Парк техники — Alpha Rent';
$ACTIVE = '';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container account-wrap" style="max-width:1000px">

    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px">
      <h1 style="font-size:28px">Парк техники</h1>
      <a href="admin.php" class="btn btn-outline">← В админ-панель</a>
    </div>

    <?php if ($msg): ?><div class="alert alert--ok"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert--error"><?= e($err) ?></div><?php endif; ?>

    <div class="grid grid-3" style="margin-bottom:22px">
      <div class="account-box" style="margin-bottom:0">
        <div style="color:var(--muted);font-size:13px">Всего велосипедов</div>
        <div style="font-family:Montserrat,sans-serif;font-size:30px;font-weight:800"><?= $stats['total'] ?></div>
      </div>
      <div class="account-box" style="margin-bottom:0">
        <div style="color:var(--muted);font-size:13px">Свободно</div>
        <div style="font-family:Montserrat,sans-serif;font-size:30px;font-weight:800;color:#2ea05a"><?= $stats['free'] ?></div>
      </div>
      <div class="account-box" style="margin-bottom:0">
        <div style="color:var(--muted);font-size:13px">В аренде / брони</div>
        <div style="font-family:Montserrat,sans-serif;font-size:30px;font-weight:800;color:var(--red)"><?= $stats['rented'] + $stats['booked'] ?></div>
      </div>
    </div>

    <div class="account-box">
      <h2>Добавить велосипеды</h2>
      <p style="color:var(--muted);font-size:14px;margin-bottom:14px">Можно добавить сразу диапазон номеров одной модели. Например: модель «Truck+», от 1 до 5.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="row row-2">
          <div class="field">
            <label for="model">Модель</label>
            <select id="model" name="model">
              <?php foreach ($models as $m): ?>
                <option value="<?= e($m) ?>"><?= e($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="status">Статус</label>
            <select id="status" name="status">
              <?php foreach ($statuses as $k => $label): ?>
                <option value="<?= e($k) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row row-2">
          <div class="field">
            <label for="from">Номер «от»</label>
            <input type="number" id="from" name="from" min="1" placeholder="Напр.: 1" required>
          </div>
          <div class="field">
            <label for="to">Номер «до» (необязательно)</label>
            <input type="number" id="to" name="to" min="1" placeholder="Пусто — один велосипед">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Добавить</button>
      </form>
    </div>

    <div class="account-box">
      <h2>Список велосипедов</h2>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
        <a href="admin_bikes.php" class="btn <?= $filter === '' ? 'btn-primary' : 'btn-ghost' ?>" style="padding:8px 14px">Все</a>
        <?php foreach ($models as $m): ?>
          <a href="admin_bikes.php?model=<?= urlencode($m) ?>" class="btn <?= $filter === $m ? 'btn-primary' : 'btn-ghost' ?>" style="padding:8px 14px"><?= e($m) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (!$bikes): ?>
        <p style="color:var(--muted)">Велосипедов пока нет. Добавьте их формой выше.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="price-table">
            <thead><tr><th>Модель</th><th>№</th><th>Статус</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($bikes as $b): ?>
                <tr>
                  <td><?= e($b['model']) ?></td>
                  <td><?= (int)$b['number'] ?></td>
                  <td>
                    <form method="post" style="display:flex;gap:6px;align-items:center">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="status">
                      <input type="hidden" name="bike_id" value="<?= (int)$b['id'] ?>">
                      <select name="status">
                        <?php foreach ($statuses as $k => $label): ?>
                          <option value="<?= e($k) ?>" <?= $b['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn btn-ghost" style="padding:7px 12px">OK</button>
                    </form>
                  </td>
                  <td>
                    <form method="post" onsubmit="return confirm('Удалить велосипед?');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="bike_id" value="<?= (int)$b['id'] ?>">
                      <button type="submit" class="btn btn-outline" style="padding:7px 12px">Удалить</button>
                    </form>
                  </td>
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
