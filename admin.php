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

$isAdmin = !empty($_SESSION['admin']);
$users = [];
if ($isAdmin) {
    $users = db()->query('SELECT id, name, phone, email, created_at FROM users ORDER BY id DESC')->fetchAll();
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
        <p class="auth-card__sub">Введите пароль администратора для доступа к списку пользователей.</p>
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
      <div class="account-wrap" style="max-width:960px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:8px">
          <h1 style="font-size:28px">Зарегистрированные пользователи</h1>
          <a href="admin.php?logout=1" class="btn btn-outline">Выйти из админки</a>
        </div>
        <p style="color:var(--muted);margin-bottom:20px">
          Всего пользователей: <b style="color:var(--white)"><?= count($users) ?></b>.
          <a href="admin_export.php" class="btn btn-primary" style="margin-left:10px">Скачать Excel</a>
        </p>

        <?php if (!$users): ?>
          <div class="alert alert--ok">Пока никто не зарегистрировался.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="price-table">
              <thead>
                <tr><th>ID</th><th>Имя</th><th>Телефон</th><th>E-mail</th><th>Дата регистрации</th></tr>
              </thead>
              <tbody>
                <?php foreach ($users as $row): ?>
                  <tr>
                    <td><?= e($row['id']) ?></td>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['phone']) ?></td>
                    <td><?= e($row['email']) ?></td>
                    <td><?= e($row['created_at']) ?></td>
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
