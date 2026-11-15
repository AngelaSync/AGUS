<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]);
$user = auth();
$roleLabel = ['customer' => 'Customer', 'shop' => 'Shop owner', 'admin' => 'Admin'];
?>
<h1>Profile</h1>

<?php if (!$user): ?>
    <div class="panel stack">
        <p style="margin:0"><strong>Welcome to Replate!</strong> Browsing is free. Sign in to reserve food or sell your shop's surplus.</p>
        <a class="btn" href="<?= e(route('login')) ?>">Sign in</a>
        <a class="btn alt" href="<?= e(route('register')) ?>">Create an account</a>
    </div>
<?php else: ?>
    <div class="panel">
        <p style="font-size:18px"><strong><?= e($user['name']) ?></strong></p>
        <p class="small"><?= e($user['email']) ?></p>
        <span class="tag t-plain"><?= e($roleLabel[$user['role']] ?? $user['role']) ?></span>
    </div>

    <h2>Your Replate</h2>
    <div class="panel stack">
        <?php if ($user['role'] === 'customer'): ?>
            <a class="btn alt" href="<?= e(route('reservations.index')) ?>">My reservations</a>
            <a class="btn alt" href="<?= e(route('shop.apply')) ?>">Sell your shop's surplus food</a>
        <?php elseif ($user['role'] === 'shop'): ?>
            <a class="btn alt" href="<?= e(route('shop.home')) ?>">My shop dashboard</a>
            <a class="btn alt" href="<?= e(route('shop.listings.create')) ?>">Post food</a>
        <?php else: ?>
            <a class="btn alt" href="<?= e(route('admin.home')) ?>">Admin: verify shops</a>
        <?php endif ?>
        <form method="post" action="<?= e(route('logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn" type="submit">Sign out</button>
        </form>
    </div>
<?php endif ?>

<h2>Help</h2>
<div class="panel">
    <p>Reserve food, show your pickup code at the shop, check the food, then pay at the counter. No online payment needed.</p>
    <p><a href="<?= e(route('about')) ?>">How Replate and AGUS work →</a></p>
</div>
