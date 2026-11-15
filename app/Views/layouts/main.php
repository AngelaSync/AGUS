<?php /** @var \Agus\View\View $this */
use App\Services\Ui;
$user = auth();
$role = $user['role'] ?? null;
[$bellUrl, $bellCount] = Ui::bell($user);
$path = request()?->path() ?? '/';
$on = fn (string ...$prefixes) => array_filter($prefixes, fn ($p) => $p === '/' ? $path === '/' || str_starts_with($path, '/listings') : str_starts_with($path, $p)) ? 'on' : '';
$third = match ($role) {
    'shop' => ['url' => route('shop.home'), 'icon' => 'dash', 'label' => 'My shop', 'paths' => ['/shop']],
    'admin' => ['url' => route('admin.home'), 'icon' => 'dash', 'label' => 'Admin', 'paths' => ['/admin']],
    default => ['url' => route('reservations.index'), 'icon' => 'res', 'label' => 'Reservations', 'paths' => ['/reservations']],
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#F6EFE3">
    <title><?= e(($title ?? '') !== '' ? $title . ' · Replate' : 'Replate') ?></title>
    <link rel="icon" href="<?= e(asset('img/replate-logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?= $this->section('head') ?>
</head>
<body>
<div class="stage"><div class="phone">
    <div class="status" aria-hidden="true"><span id="clock"><?= e(date('g:i')) ?></span><span class="notch"></span>
        <span class="sicons"><svg viewBox="0 0 18 12" width="18" height="12"><rect x="0" y="8" width="3" height="4" rx="1" fill="currentColor"/><rect x="5" y="5" width="3" height="7" rx="1" fill="currentColor"/><rect x="10" y="2" width="3" height="10" rx="1" fill="currentColor"/><rect x="15" y="0" width="3" height="12" rx="1" fill="currentColor"/></svg>
        <svg viewBox="0 0 16 12" width="16" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M1 4.5a10 10 0 0 1 14 0M3.5 7.2a6.5 6.5 0 0 1 9 0"/><circle cx="8" cy="10" r="1.2" fill="currentColor" stroke="none"/></svg>
        <svg viewBox="0 0 26 12" width="26" height="12"><rect x="0.5" y="0.5" width="22" height="11" rx="3" fill="none" stroke="currentColor" opacity=".5"/><rect x="2" y="2" width="16" height="8" rx="1.8" fill="currentColor"/><rect x="24" y="4" width="2" height="4" rx="1" fill="currentColor" opacity=".5"/></svg></span></div>

    <div class="screen" id="screen"><div class="app">
        <header class="top">
            <a href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/replate-logo.png')) ?>" alt="Replate: Don't waste it. Replate it." width="74" height="44"></a>
            <a class="iconbtn" href="<?= e($bellUrl) ?>" aria-label="<?= $bellCount ? e($bellCount . ' updates') : 'Updates' ?>">
                <?= Ui::icon('bell') ?><?php if ($bellCount): ?><span class="dot"><?= (int) $bellCount ?></span><?php endif ?>
            </a>
        </header>
        <main>
            <?= $this->insert('partials/flash') ?>
            <?= $this->section('content') ?>
        </main>
        <p class="footer-note">Built on AGUS, my own PHP framework · <a href="<?= e(route('about')) ?>">How it works</a></p>
    </div></div>

    <nav class="bar" aria-label="Main"><div class="in">
        <a class="<?= $on('/') ?>" href="<?= e(route('home')) ?>"><?= Ui::icon('browse') ?>Browse</a>
        <a class="<?= $on('/map') ?>" href="<?= e(route('map')) ?>"><?= Ui::icon('map') ?>Map</a>
        <a class="<?= $on(...$third['paths']) ?>" href="<?= e($third['url']) ?>"><?= Ui::icon($third['icon']) ?><?= e($third['label']) ?><?php if ($bellCount && $role): ?><span class="dot"><?= (int) $bellCount ?></span><?php endif ?></a>
        <a class="<?= $on('/profile', '/login', '/register', '/sell', '/about') ?>" href="<?= e(route('profile')) ?>"><?= Ui::icon('user') ?>Profile</a>
    </div></nav>
</div></div>
<p class="caption">Replate · running on AGUS (PHP) · resize below 700px for the full-screen phone view</p>
<script>
    // Status-bar clock, like the prototype
    (function tick() { var d = new Date(), el = document.getElementById('clock'); if (el) el.textContent = (d.getHours() % 12 || 12) + ':' + String(d.getMinutes()).padStart(2, '0'); setTimeout(tick, 15000); })();
</script>
<?= $this->section('scripts') ?>
</body>
</html>
