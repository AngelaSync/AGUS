<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]);
$pinForm = function ($shop): string {
    return '<form method="post" action="' . e(route('admin.shops.location', ['id' => $shop->id])) . '" class="grid2" style="margin-top:8px">' . csrf_field()
        . '<input name="latitude" inputmode="decimal" placeholder="Latitude" value="' . e((string) $shop->latitude) . '" aria-label="Latitude" style="border:1.5px solid var(--line);border-radius:12px;padding:8px 10px;background:var(--bg)">'
        . '<input name="longitude" inputmode="decimal" placeholder="Longitude" value="' . e((string) $shop->longitude) . '" aria-label="Longitude" style="border:1.5px solid var(--line);border-radius:12px;padding:8px 10px;background:var(--bg)">'
        . '<button class="btn alt small" type="submit" style="grid-column:1/-1">Save map pin</button></form>';
};
?>
<h1>Admin</h1>
<p class="sub">Verify new shops before their food goes live.</p>

<div class="stats">
    <div class="stat"><b><?= (int) $stats['users'] ?></b><span>accounts</span></div>
    <div class="stat"><b><?= (int) $stats['shops'] ?></b><span>shops</span></div>
    <div class="stat"><b><?= (int) $stats['listings'] ?></b><span>live listings</span></div>
    <div class="stat"><b><?= (int) $stats['rescued'] ?></b><span>items rescued</span></div>
</div>

<h2>Waiting for verification</h2>
<?php if (!$pending): ?>
    <div class="panel empty">All caught up. No shops waiting.</div>
<?php else: ?>
    <div class="stack">
        <?php foreach ($pending as $shop): ?>
            <div class="panel">
                <div class="row between"><strong><?= e($shop->name) ?></strong><span class="tag t-warn">Pending</span></div>
                <p class="small"><?= e($shop->address) ?></p>
                <?php if (!$shop->hasPin()): ?><p class="small">Not found on the map. Place the pin by hand:</p><?php endif ?>
                <?= $pinForm($shop) ?>
                <form method="post" action="<?= e(route('admin.shops.verify', ['id' => $shop->id])) ?>" style="margin-top:8px">
                    <?= csrf_field() ?>
                    <button class="btn good" type="submit">Verify shop</button>
                </form>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<h2>Verified shops</h2>
<div class="panel">
    <?php foreach ($verified as $shop): ?>
        <div class="item">
            <div class="grow"><b><?= e($shop->name) ?></b><span class="small"><?= e($shop->address) ?></span></div>
            <form method="post" action="<?= e(route('admin.shops.verify', ['id' => $shop->id])) ?>" class="inline">
                <?= csrf_field() ?>
                <button class="btn danger small" type="submit">Hide</button>
            </form>
        </div>
    <?php endforeach ?>
</div>

<h2>Accounts</h2>
<div class="panel">
    <?php foreach ($users['data'] as $u): ?>
        <div class="item">
            <div class="grow"><b><?= e($u->name) ?></b><span class="small"><?= e($u->email) ?></span></div>
            <span class="tag t-plain"><?= e($u->role) ?></span>
        </div>
    <?php endforeach ?>
</div>
<?php if ($users['last_page'] > 1): ?>
    <div class="chips">
        <?php for ($p = 1; $p <= $users['last_page']; $p++): ?>
            <a class="chip <?= $p === $users['current_page'] ? 'on' : '' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
        <?php endfor ?>
    </div>
<?php endif ?>
