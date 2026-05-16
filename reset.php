<?php
require __DIR__ . '/includes/db.php';

if (current_user()) {
    header('Location: account.php');
    exit;
}

$errors = [];
$info   = '';
$stage  = 'request'; // request | setnew | done
$token  = trim((string)($_GET['token'] ?? ($_POST['token'] ?? '')));
$tokenUser = null;

// Проверка токена восстановления
if ($token !== '') {
    $st = db()->prepare('SELECT * FROM users WHERE reset_token = ?');
    $st->execute([$token]);
    $tokenUser = $st->fetch();
    if ($tokenUser && $tokenUser['reset_expires'] && $tokenUser['reset_expires'] >= date('Y-m-d H:i:s')) {
        $stage = 'setnew';
    } else {
        $tokenUser = null;
        $token = '';
        $errors[] = 'Ссылка восстановления недействительна или устарела. Запросите новую.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check()) {
        $errors[] = 'Сессия устарела, обновите страницу.';
    } elseif ($action === 'request') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Укажите корректный e-mail.';
        } else {
            $st = db()->prepare('SELECT * FROM users WHERE email = ?');
            $st->execute([$email]);
            $row = $st->fetch();
            if ($row) {
                $rtoken = bin2hex(random_bytes(32));
                db()->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
                    ->execute([$rtoken, date('Y-m-d H:i:s', time() + 3600), $row['id']]);
                send_reset_email($row['email'], $row['name'], $rtoken);
            }
            $info = 'Если такой e-mail зарегистрирован, мы отправили на него ссылку для восстановления пароля. Проверьте почту и папку «Спам».';
        }
    } elseif ($action === 'setnew') {
        if ($stage !== 'setnew' || !$tokenUser) {
            $errors[] = 'Ссылка восстановления недействительна или устарела.';
        } else {
            $new  = (string)($_POST['new'] ?? '');
            $new2 = (string)($_POST['new2'] ?? '');
            if (mb_strlen($new) < 6) { $errors[] = 'Пароль должен быть не короче 6 символов.'; }
            if ($new !== $new2)      { $errors[] = 'Пароли не совпадают.'; }
            if (!$errors) {
                db()->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $tokenUser['id']]);
                $stage = 'done';
            }
        }
    }
}

$PAGE_TITLE = 'Восстановление пароля — Alpha Rent';
$ACTIVE = 'account';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container">
    <div class="auth-card reveal">

    <?php if ($stage === 'done'): ?>
      <h1>Пароль изменён</h1>
      <p class="auth-card__sub">Новый пароль сохранён. Теперь вы можете войти в личный кабинет.</p>
      <a href="login.php" class="btn btn-primary btn-block btn-lg">Перейти ко входу</a>

    <?php elseif ($stage === 'setnew'): ?>
      <h1>Новый пароль</h1>
      <p class="auth-card__sub">Придумайте новый пароль для входа в личный кабинет.</p>
      <?php if ($errors): ?>
        <div class="alert alert--error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="setnew">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
          <label for="new">Новый пароль</label>
          <input type="password" id="new" name="new" placeholder="Не короче 6 символов" required>
        </div>
        <div class="field">
          <label for="new2">Повторите пароль</label>
          <input type="password" id="new2" name="new2" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Сохранить пароль</button>
      </form>

    <?php else: ?>
      <h1>Восстановление пароля</h1>
      <p class="auth-card__sub">Укажите e-mail, на который зарегистрирован аккаунт — мы отправим ссылку для сброса пароля.</p>
      <?php if ($info): ?>
        <div class="alert alert--ok"><?= e($info) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert--error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <?php if ($info === ''): ?>
        <form method="post" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="request">
          <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg">Отправить ссылку</button>
        </form>
      <?php endif; ?>
      <p class="auth-switch"><a href="login.php">Вернуться ко входу</a></p>
    <?php endif; ?>

    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
