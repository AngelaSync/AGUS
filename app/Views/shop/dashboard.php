<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]);
/** @var \App\Models\Shop $shop */
$waiting = array_values(array_filter($reservations, fn ($r) => $r['status'] === 'reserved'));
$done = array_values(array_filter($reservations, fn ($r) => $r['status'] !== 'reserved'));
$rescued = array_sum(array_map(fn ($r) => $r['status'] === 'picked_up' ? (int) $r['quantity'] : 0, $reservations));
?>
<h1><?= e($shop->name) ?></h1>
<p class="sub"><?= e($shop->address) ?></p>

<?php if ($shop->isVerified()): ?>
    <div class="notice ok">Verified. Your food is live for customers.</div>
<?php else: ?>
    <div class="notice">Waiting for an admin to verify your shop. You can post food now; customers see it once you're verified.</div>
<?php endif ?>

<div class="stats" style="margin:12px 0">
    <div class="stat"><b><?= count($waiting) ?></b><span>waiting for pickup</span></div>
    <div class="stat"><b><?= $rescued ?></b><span>items rescued</span></div>
    <div class="stat"><b><?= count($listings) ?></b><span>listings</span></div>
    <div class="stat"><b><?= $shop->hasPin() ? 'Yes' : 'Not yet' ?></b><span>on the map</span></div>
</div>
<a class="btn" href="<?= e(route('shop.listings.create')) ?>">+ Post food</a>

<h2>Pickups</h2>
<?php if (!$reservations): ?>
    <div class="panel empty">No reservations yet. They'll show up here with their codes.</div>
<?php else: ?>
    <div class="panel">
        <?php foreach (array_merge($waiting, $done) as $r): ?>
            <div class="item">
                <span class="code small"><?= e($r['pickup_code']) ?></span>
                <div class="grow">
                    <b><?= (int) $r['quantity'] ?> × <?= e($r['listing_name']) ?></b>
                    <span class="small"><?= e($r['customer_name']) ?> · <?= e(money($r['total'], 0)) ?></span>
                </div>
                <?php if ($r['status'] === 'reserved'): ?>
                    <form method="post" action="<?= e(route('shop.reservations.pickup', ['id' => $r['id']])) ?>" class="inline">
                        <?= csrf_field() ?>
                        <button class="btn good small" type="submit">Picked up</button>
                    </form>
                <?php else: ?>
                    <span class="tag <?= $r['status'] === 'picked_up' ? 't-ok' : 't-bad' ?>"><?= $r['status'] === 'picked_up' ? 'Done' : 'Cancelled' ?></span>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<h2>My listings</h2>
<?php if (!$listings): ?>
    <div class="panel empty">Nothing posted yet. Tap “Post food” to add your first listing.</div>
<?php else: ?>
    <div class="panel">
        <?php foreach ($listings as $l): $expired = (string) $l->best_before < date('Y-m-d'); ?>
            <div class="item">
                <?= $this->insert('partials/listing-photo', ['listing' => $l, 'size' => 'sm']) ?>
                <div class="grow">
                    <b><?= e($l->name) ?></b>
                    <span class="small"><?= e(money($l->price, 0)) ?> · <?= (int) $l->quantity ?> left · <?= e($l->pickup_window) ?></span>
                    <?php if ((int) $l->quantity === 0): ?><span class="tag t-bad">Sold out</span><?php elseif ($expired): ?><span class="tag t-bad">Expired</span><?php endif ?>
                </div>
                <div class="stack" style="gap:6px">
                    <a class="btn alt small" href="<?= e(route('shop.listings.edit', ['id' => $l->id])) ?>">Edit</a>
                    <form method="post" action="<?= e(route('shop.listings.destroy', ['id' => $l->id])) ?>" class="inline" onsubmit="return confirm('Remove this listing?')">
                        <?= csrf_field() ?>
                        <button class="btn danger small" type="submit" style="width:100%">Remove</button>
                    </form>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
