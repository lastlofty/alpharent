<?php
require __DIR__ . '/includes/db.php';

$PAGE_TITLE = 'Оплата принята — Alpha Rent';
$ACTIVE = '';
require __DIR__ . '/includes/header.php';
?>
<section class="section auth-section">
  <div class="container">
    <div class="auth-card reveal" style="text-align:center">
      <div style="width:74px;height:74px;border-radius:50%;background:#2ea05a;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h1>Спасибо за оплату!</h1>
      <p class="auth-card__sub">Платёж принят и обрабатывается. Мы подтвердим поступление средств и активируем вашу аренду — обычно это занимает несколько минут.</p>
      <div class="alert alert--ok">
        Сумма в личном кабинете обновится после подтверждения платежа. Если этого не произошло в течение часа — позвоните нам: +7 (995) 687-03-04.
      </div>
      <a href="account.php" class="btn btn-primary btn-block btn-lg">В личный кабинет</a>
      <p class="auth-switch"><a href="index.html">Вернуться на главную</a></p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
