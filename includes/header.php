<?php
if (!isset($PAGE_TITLE)) { $PAGE_TITLE = 'Alpha Rent'; }
if (!isset($ACTIVE))     { $ACTIVE = ''; }
if (!isset($NOINDEX))    { $NOINDEX = true; }
$__u = function_exists('current_user') ? current_user() : null;
function nav_active($key, $cur) { return $key === $cur ? ' class="active"' : ''; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($PAGE_TITLE) ?></title>
<?php if ($NOINDEX): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="icon" href="assets/logo.jpg" type="image/jpeg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=8">
<!-- Yandex.Metrika counter — впишите номер вашего счётчика вместо 99999999 -->
<script type="text/javascript">
  (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();
  for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}
  k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
  (window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
  ym(99999999,"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/99999999" style="position:absolute;left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
</head>
<body>

<header class="header">
  <div class="container header__inner">
    <a href="index.html" class="logo" aria-label="Alpha Rent — на главную">
      <img src="assets/logo.jpg" alt="Логотип Alpha Rent">
      <span class="logo__text">
        <span class="logo__name">ALPHA<span> RENT</span></span>
        <span class="logo__tag">Аренда|Ремонт<br>электровелосипедов</span>
      </span>
    </a>
    <span class="city-badge" title="Аренда электровелосипедов в Казани">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      Казань
    </span>
    <nav class="nav" id="nav">
      <a href="index.html"<?= nav_active('index', $ACTIVE) ?>>Главная</a>
      <a href="arenda.html"<?= nav_active('arenda', $ACTIVE) ?>>Аренда</a>
      <a href="remont.html"<?= nav_active('remont', $ACTIVE) ?>>Ремонт</a>
      <a href="vykup.html"<?= nav_active('vykup', $ACTIVE) ?>>Выкуп</a>
      <a href="kontakty.html"<?= nav_active('kontakty', $ACTIVE) ?>>Контакты</a>
      <a href="account.php"<?= nav_active('account', $ACTIVE) ?>>Кабинет</a>
    </nav>
    <a href="tel:+79956870304" class="header__phone">+7 (995) 687-03-04<span>Альфа Рент</span></a>
    <?php if ($__u): ?>
      <a href="logout.php" class="btn btn-primary header__cta">Выйти</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-primary header__cta">Войти</a>
    <?php endif; ?>
    <button class="burger" id="burger" aria-label="Меню"><span></span><span></span><span></span></button>
  </div>
</header>

<main>
