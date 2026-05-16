<?php
require __DIR__ . '/includes/db.php';

if (current_user()) {
    header('Location: account.php');
    exit;
}

$errors = [];
$info = '';
$email = '';
$needVerify = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
    } else {
        $action = $_POST['action'] ?? 'login';
        $email  = trim($_POST['email'] ?? '');

        if ($action === 'resend') {
            $st = db()->prepare('SELECT * FROM users WHERE email = ?');
            $st->execute([$email]);
            $row = $st->fetch();
            if ($row && (int)$row['is_verified'] === 0) {
                $token = bin2hex(random_bytes(32));
                db()->prepare('UPDATE users SET verify_token = ? WHERE id = ?')->execute([$token, $row['id']]);
                send_verification_email($row['email'], $row['name'], $token);
                $info = 'Письмо с подтверждением отправлено повторно на ' . $email . '. Проверьте почту и папку «Спам».';
            } elseif ($row) {
                $info = 'Этот e-mail уже подтверждён — просто войдите.';
            } else {
                $info = 'Если такой e-mail зарегистрирован, письмо отправлено.';
            }
        } else {
            $pass = (string)($_POST['password'] ?? '');
            $ip = client_ip();
            if (login_too_many($ip)) {
                $errors[] = 'Слишком много попыток входа. Попробуйте снова через 15 минут.';
            } else {
                $st = db()->prepare('SELECT * FROM users WHERE email = ?');
                $st->execute([$email]);
                $row = $st->fetch();
                if ($row && password_verify($pass, $row['password_hash'])) {
                    if ((int)$row['is_verified'] === 1) {
                        session_regenerate_id(true);
                        $_SESSION['uid'] = (int)$row['id'];
                        header('Location: account.php');
                        exit;
                    }
                    $needVerify = true;
                    $errors[] = 'E-mail не подтверждён. Перейдите по ссылке из письма, которое мы отправили при регистрации.';
                } else {
                    login_record_fail($ip);
                    $errors[] = 'Неверный e-mail или пароль.';
                }
            }
        }
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

      <?php if ($info): ?>
        <div class="alert alert--ok"><?= e($info) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert--error">
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($needVerify): ?>
        <form method="post" style="margin-bottom:18px">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="resend">
          <input type="hidden" name="email" value="<?= e($email) ?>">
          <button type="submit" class="btn btn-outline btn-block">Отправить письмо повторно</button>
        </form>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
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

      <p class="auth-switch"><a href="reset.php">Забыли пароль?</a></p>
      <p class="auth-switch" style="margin-top:8px">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
