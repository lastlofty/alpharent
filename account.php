<?php
require __DIR__ . '/includes/db.php';
require_login();

$u = current_user();
$errors = [];
$okMsg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $name  = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if (mb_strlen($name) < 2)  { $errors[] = 'Укажите имя.'; }
            if (mb_strlen($phone) < 6) { $errors[] = 'Укажите телефон.'; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Укажите корректный e-mail.'; }
            if (!$errors) {
                $st = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
                $st->execute([$email, $u['id']]);
                if ($st->fetch()) {
                    $errors[] = 'Этот e-mail уже используется другим пользователем.';
                } else {
                    db()->prepare('UPDATE users SET name = ?, phone = ?, email = ? WHERE id = ?')
                        ->execute([$name, $phone, $email, $u['id']]);
                    $okMsg = 'Профиль обновлён.';
                }
            }
        } elseif ($action === 'password') {
            $cur  = (string)($_POST['current'] ?? '');
            $new  = (string)($_POST['new'] ?? '');
            $new2 = (string)($_POST['new2'] ?? '');
            if (!password_verify($cur, $u['password_hash'])) { $errors[] = 'Текущий пароль введён неверно.'; }
            if (mb_strlen($new) < 6) { $errors[] = 'Новый пароль должен быть не короче 6 символов.'; }
            if ($new !== $new2)      { $errors[] = 'Новые пароли не совпадают.'; }
            if (!$errors) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
                $okMsg = 'Пароль изменён.';
            }
        }
    }
    // обновляем данные пользователя после изменений
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
}

$PAGE_TITLE = 'Личный кабинет — Alpha Rent';
$ACTIVE = 'account';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <div class="breadcrumbs"><a href="index.html">Главная</a> / Личный кабинет</div>
    <h1>Здравствуйте, <?= e($u['name']) ?>!</h1>
    <p>Это ваш личный кабинет Alpha Rent. Здесь можно изменить данные профиля и пароль.</p>
  </div>
</section>

<section class="section">
  <div class="container account-wrap">

    <?php if ($okMsg): ?>
      <div class="alert alert--ok"><?= e($okMsg) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert--error">
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="account-box">
      <h2>Мои данные</h2>
      <div class="data-row"><span>Имя</span><b><?= e($u['name']) ?></b></div>
      <div class="data-row"><span>Телефон</span><b><?= e($u['phone']) ?></b></div>
      <div class="data-row"><span>E-mail</span><b><?= e($u['email']) ?></b></div>
      <div class="data-row"><span>Дата регистрации</span><b><?= e($u['created_at']) ?></b></div>
      <a href="logout.php" class="btn btn-outline" style="margin-top:18px">Выйти из кабинета</a>
    </div>

    <div class="account-box">
      <h2>Редактировать профиль</h2>
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="profile">
        <div class="field">
          <label for="name">Имя</label>
          <input type="text" id="name" name="name" value="<?= e($u['name']) ?>" required>
        </div>
        <div class="field">
          <label for="phone">Телефон</label>
          <input type="tel" id="phone" name="phone" value="<?= e($u['phone']) ?>" required>
        </div>
        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" value="<?= e($u['email']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
      </form>
    </div>

    <div class="account-box">
      <h2>Сменить пароль</h2>
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="password">
        <div class="field">
          <label for="current">Текущий пароль</label>
          <input type="password" id="current" name="current" required>
        </div>
        <div class="field">
          <label for="new">Новый пароль</label>
          <input type="password" id="new" name="new" placeholder="Не короче 6 символов" required>
        </div>
        <div class="field">
          <label for="new2">Повторите новый пароль</label>
          <input type="password" id="new2" name="new2" required>
        </div>
        <button type="submit" class="btn btn-primary">Изменить пароль</button>
      </form>
    </div>

  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
