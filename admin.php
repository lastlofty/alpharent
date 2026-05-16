<?php
require __DIR__ . '/includes/db.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    header('Location: admin.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    if (csrf_check() && hash_equals(ADMIN_PASSWORD, (string)$_POST['admin_pass'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Неверный пароль.';
}

$isAdmin = is_admin();
$users = [];
$totalDebt = 0;
if ($isAdmin) {
    $users = db()->query(
        'SELECT id, name, phone, tariff_model, weekly_price, debt, free_days, rental_active
         FROM users ORDER BY debt DESC, id DESC'
    )->fetchAll();
    foreach ($users as $r) {
        $totalDebt += (int)$r['debt'];
    }
}

$PAGE_TITLE = 'Админ-панель — Alpha Rent';
$ACTIVE = '';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container">

  <?php if (!$isAdmin): ?>
    <div class="auth-card reveal">
      <h1>Админ-панель</h1>
      <p class="auth-card__sub">Введите пароль администратора для доступа.</p>
      <?php if ($loginError): ?>
        <div class="alert alert--error"><?= e($loginError) ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label for="admin_pass">Пароль администратора</label>
          <input type="password" id="admin_pass" name="admin_pass" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Войти</button>
      </form>
    </div>

  <?php else: ?>
    <div class="account-wrap" style="max-width:1040px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px">
        <h1 style="font-size:28px">Админ-панель</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <a href="admin_bikes.php" class="btn btn-primary">Парк техники</a>
          <a href="admin.php?logout=1" class="btn btn-outline">Выйти из админки</a>
        </div>
      </div>

      <div class="grid grid-3" style="margin-bottom:22px">
        <div class="account-box" style="margin-bottom:0">
          <div style="color:var(--muted);font-size:13px">Пользователей</div>
          <div style="font-family:Montserrat,sans-serif;font-size:30px;font-weight:800"><?= count($users) ?></div>
        </div>
        <div class="account-box" style="margin-bottom:0">
          <div style="color:var(--muted);font-size:13px">Общий долг</div>
          <div style="font-family:Montserrat,sans-serif;font-size:30px;font-weight:800;color:var(--red)"><?= money($totalDebt) ?></div>
        </div>
        <div class="account-box" style="margin-bottom:0;display:flex;align-items:center">
          <a href="admin_export.php" class="btn btn-primary btn-block">Скачать список (Excel)</a>
        </div>
      </div>

      <?php if (!$users): ?>
        <div class="alert alert--ok">Пока никто не зарегистрировался.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="price-table">
            <thead>
              <tr><th>Имя</th><th>Телефон</th><th>Модель</th><th>Цена/нед</th><th>Долг</th><th>Беспл. дни</th><th>Аренда</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $r): ?>
                <tr>
                  <td><?= e($r['name']) ?></td>
                  <td><?= e($r['phone']) ?></td>
                  <td><?= $r['tariff_model'] ? e($r['tariff_model']) : '—' ?></td>
                  <td><?= (int)$r['weekly_price'] > 0 ? money($r['weekly_price']) : '—' ?></td>
                  <td class="<?= (int)$r['debt'] > 0 ? 'accent' : '' ?>"><?= money($r['debt']) ?></td>
                  <td><?= (int)$r['free_days'] ?></td>
                  <td><?= ((int)$r['rental_active'] === 1) ? 'активна' : 'нет' ?></td>
                  <td><a href="admin_user.php?id=<?= (int)$r['id'] ?>" class="btn btn-ghost">Управление</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
