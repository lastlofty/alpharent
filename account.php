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
            if (!valid_ru_phone($phone)) { $errors[] = 'Укажите корректный номер телефона: +7 и 10 цифр.'; }
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
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
}

// История биллинга
$histSt = db()->prepare('SELECT * FROM billing_log WHERE user_id = ? ORDER BY id DESC LIMIT 30');
$histSt->execute([$u['id']]);
$history = $histSt->fetchAll();

$payReady = defined('PAYMENT_LINK') && PAYMENT_LINK !== '' && mb_strpos((string)PAYMENT_LINK, 'ВПИШИТЕ') === false;

$PAGE_TITLE = 'Личный кабинет — Alpha Rent';
$ACTIVE = 'account';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <div class="breadcrumbs"><a href="index.html">Главная</a> / Личный кабинет</div>
    <h1>Здравствуйте, <?= e($u['name']) ?>!</h1>
    <p>Здесь — ваша аренда, сумма к оплате, история начислений и данные профиля.</p>
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
      <h2>Аренда и оплата</h2>
      <?php if ($u['tariff_model']): ?>
        <div class="data-row"><span>Модель</span><b><?= e($u['tariff_model']) ?></b></div>
        <div class="data-row"><span>Стоимость недели</span><b><?= money($u['weekly_price']) ?></b></div>
        <div class="data-row"><span>Бесплатные дни</span><b><?= (int)$u['free_days'] ?></b></div>
        <div class="data-row">
          <span>Статус аренды</span>
          <b><?= ((int)$u['rental_active'] === 1) ? 'Активна' : 'Приостановлена' ?></b>
        </div>
        <?php if ((int)$u['rental_active'] === 1 && $u['next_charge_at']): ?>
          <div class="data-row"><span>Следующее начисление</span><b><?= e(date('d.m.Y', strtotime($u['next_charge_at']))) ?></b></div>
        <?php endif; ?>

        <div style="margin:18px 0;padding:18px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm)">
          <div style="color:var(--muted);font-size:13px">К оплате</div>
          <div style="font-family:Montserrat,sans-serif;font-size:34px;font-weight:800;color:<?= ((int)$u['debt'] > 0) ? 'var(--red)' : 'var(--white)' ?>">
            <?= money($u['debt']) ?>
          </div>
        </div>

        <?php if ((int)$u['debt'] > 0): ?>
          <?php if ($payReady): ?>
            <a href="<?= e(PAYMENT_LINK) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-block btn-lg">Оплатить онлайн</a>
            <p class="form__note">Оплата через Точку Банк. Сумма обновится после того, как мы подтвердим поступление платежа.</p>
          <?php else: ?>
            <div class="alert alert--error">Онлайн-оплата ещё настраивается. Для оплаты свяжитесь с нами: +7 (995) 687-03-04.</div>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert--ok">Задолженности нет — спасибо!</div>
        <?php endif; ?>
      <?php else: ?>
        <p style="color:var(--muted)">Аренда пока не оформлена. Выберите электровелосипед в <a href="arenda.html" style="color:var(--red)">каталоге</a> или позвоните нам: +7 (995) 687-03-04.</p>
      <?php endif; ?>

      <?php if ($history): ?>
        <h4 style="margin-top:22px;font-size:15px">История операций</h4>
        <div class="table-wrap" style="margin-top:10px">
          <table class="price-table">
            <thead><tr><th>Дата</th><th>Операция</th><th>Сумма / дни</th><th>Комментарий</th></tr></thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= e(date('d.m.Y H:i', strtotime($h['created_at']))) ?></td>
                  <td><?= e(billing_type_label($h['type'])) ?></td>
                  <td>
                    <?php if ((int)$h['days'] > 0 && $h['type'] === 'free_days'): ?>
                      +<?= (int)$h['days'] ?> дн.
                    <?php elseif ((int)$h['amount'] !== 0): ?>
                      <?php $isMinus = in_array($h['type'], ['payment'], true) || (int)$h['amount'] < 0; ?>
                      <?= $isMinus ? '−' : '+' ?><?= money(abs((int)$h['amount'])) ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td><?= e($h['comment']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

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
