<?php
require __DIR__ . '/includes/db.php';
require_admin();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    if (($_POST['action'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM requests WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $msg = 'Заявка удалена.';
    }
}

$requests = db()->query('SELECT * FROM requests ORDER BY id DESC')->fetchAll();

$PAGE_TITLE = 'Заявки — Alpha Rent';
$ACTIVE = '';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container account-wrap" style="max-width:980px">

    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px">
      <h1 style="font-size:28px">Заявки с сайта</h1>
      <a href="admin.php" class="btn btn-outline">← В админ-панель</a>
    </div>

    <?php if ($msg): ?><div class="alert alert--ok"><?= e($msg) ?></div><?php endif; ?>

    <p style="color:var(--muted);margin-bottom:18px">Всего заявок: <b style="color:var(--white)"><?= count($requests) ?></b>. Заявки также дублируются в ваш Telegram.</p>

    <?php if (!$requests): ?>
      <div class="alert alert--ok">Заявок пока нет.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="price-table">
          <thead>
            <tr><th>Дата</th><th>Имя</th><th>Телефон</th><th>Детали</th><th>Страница</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
              <tr>
                <td><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['phone']) ?></td>
                <td><?= e($r['details']) ?></td>
                <td><?= e($r['source']) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Удалить заявку?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
