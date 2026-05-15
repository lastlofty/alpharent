<?php
require __DIR__ . '/includes/db.php';

if (current_user()) {
    header('Location: account.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');

        $st = db()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();

        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$row['id'];
            header('Location: account.php');
            exit;
        }
        $errors[] = 'Неверный e-mail или пароль.';
    }
}

$PAGE_TITLE = 'Вход — Alpha Rent';
$ACTIVE = 'account';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container">
    <div class="auth-card reveal">
      <h1>Вход в кабинет</h1>
      <p class="auth-card__sub">Войдите в личный кабинет Alpha Rent.</p>

      <?php if ($errors): ?>
        <div class="alert alert--error">
          <ul>
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" required>
        </div>
        <div class="field">
          <label for="password">Пароль</label>
          <input type="password" id="password" name="password" placeholder="Ваш пароль" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Войти</button>
      </form>

      <p class="auth-switch">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
