<?php
require __DIR__ . '/includes/db.php';

if (current_user()) {
    header('Location: account.php');
    exit;
}

$errors = [];
$name = $phone = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');

        if (mb_strlen($name) < 2)  { $errors[] = 'Укажите имя.'; }
        if (mb_strlen($phone) < 6) { $errors[] = 'Укажите телефон.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Укажите корректный e-mail.'; }
        if (mb_strlen($pass) < 6)  { $errors[] = 'Пароль должен быть не короче 6 символов.'; }
        if ($pass !== $pass2)      { $errors[] = 'Пароли не совпадают.'; }

        if (!$errors) {
            $st = db()->prepare('SELECT id FROM users WHERE email = ?');
            $st->execute([$email]);
            if ($st->fetch()) {
                $errors[] = 'Пользователь с таким e-mail уже зарегистрирован.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (name, phone, email, password_hash, created_at) VALUES (?,?,?,?,?)')
                    ->execute([$name, $phone, $email, $hash, date('Y-m-d H:i:s')]);
                append_user_csv($name, $phone, $email);
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)db()->lastInsertId();
                header('Location: account.php');
                exit;
            }
        }
    }
}

$PAGE_TITLE = 'Регистрация — Alpha Rent';
$ACTIVE = 'account';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container">
    <div class="auth-card reveal">
      <h1>Регистрация</h1>
      <p class="auth-card__sub">Создайте личный кабинет Alpha Rent — это бесплатно и займёт минуту.</p>

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
          <label for="name">Имя</label>
          <input type="text" id="name" name="name" value="<?= e($name) ?>" placeholder="Как к вам обращаться" required>
        </div>
        <div class="field">
          <label for="phone">Телефон</label>
          <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+7 (___) ___-__-__" required>
        </div>
        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" required>
        </div>
        <div class="field">
          <label for="password">Пароль</label>
          <input type="password" id="password" name="password" placeholder="Не короче 6 символов" required>
        </div>
        <div class="field">
          <label for="password2">Повторите пароль</label>
          <input type="password" id="password2" name="password2" placeholder="Ещё раз тот же пароль" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Зарегистрироваться</button>
        <p class="form__note">Нажимая кнопку, вы соглашаетесь с обработкой персональных данных.</p>
      </form>

      <p class="auth-switch">Уже есть аккаунт? <a href="login.php">Войти</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
