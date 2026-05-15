<?php
require __DIR__ . '/includes/db.php';

if (current_user()) {
    header('Location: account.php');
    exit;
}

$ok = false;
$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

if ($token !== '') {
    $st = db()->prepare('SELECT * FROM users WHERE verify_token = ?');
    $st->execute([$token]);
    $row = $st->fetch();
    if ($row) {
        db()->prepare('UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?')
            ->execute([$row['id']]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['id'];
        header('Location: account.php');
        exit;
    }
}

$PAGE_TITLE = 'Подтверждение e-mail — Alpha Rent';
$ACTIVE = 'account';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container">
    <div class="auth-card reveal">
      <h1>Ссылка недействительна</h1>
      <p class="auth-card__sub">Ссылка подтверждения устарела или уже была использована.</p>
      <div class="alert alert--error">
        Если вы уже подтвердили почту — просто войдите. Если нет — запросите новое письмо на странице входа.
      </div>
      <a href="login.php" class="btn btn-primary btn-block btn-lg">Перейти ко входу</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
