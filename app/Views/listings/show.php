<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]);
/** @var \App\Models\Listing $listing */
use App\Services\Ui;
$role = auth()['role'] ?? null;
$max = min(10, (int) $listing->quantity);
?>
<div class="sheethead">
    <h1><?= e($listing->name) ?></h1>
    <a class="close" href="<?= e(url('/')) ?>" aria-label="Back to all food">×</a>
</div>

<?= $this->insert('partials/listing-photo', ['listing' => $listing, 'size' => 'big']) ?>

<div class="row between" style="margin:12px 0 6px">
    <span class="price" style="font-size:24px"><?= e(money($listing->price, 0)) ?><s><?= e(money($listing->original_price, 0)) ?></s></span>
    <span class="tag t-left"><?= (int) $listing->quantity ?> left</span>
</div>
<?php if ($listing->description): ?><p style="margin:0 0 12px"><?= e($listing->description) ?></p><?php endif ?>

<div class="panel facts">
    <p><strong>Pickup:</strong> Today, <?= e($listing->pickup_window) ?></p>
    <p><strong>Best before:</strong> <?= e(date('l, M j', strtotime((string) $listing->best_before))) ?></p>
    <p><strong>Allergens:</strong> <?= e($listing->allergens ?: 'None listed. Ask the shop if unsure.') ?></p>
    <p><strong>Shop:</strong> <a href="<?= e(route('map')) ?>"><?= e($listing->shop_name) ?></a> <span class="verified"><?= Ui::icon('check') ?> Verified</span> · <?= e($listing->shop_address) ?></p>
</div>

<div class="notice lav" style="margin:14px 0">Check, then pay at the counter. No online payment needed.</div>

<?php if ($role === 'customer'): ?>
    <form method="post" action="<?= e(route('reservations.store', ['id' => $listing->id])) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="row between">
            <strong>Quantity</strong>
            <div class="qty">
                <button type="button" data-step="-1" aria-label="One less">−</button>
                <b id="qty-show">1</b>
                <button type="button" data-step="1" aria-label="One more">+</button>
            </div>
        </div>
        <input type="hidden" name="quantity" id="quantity" value="1">
        <?php if ($err = errors('quantity')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
        <button class="btn" type="submit" id="reserve-btn">Reserve · <?= e(money($listing->price, 0)) ?></button>
    </form>
<?php elseif ($role === null): ?>
    <div class="stack">
        <a class="btn" href="<?= e(route('login')) ?>">Sign in to reserve</a>
        <a class="btn alt" href="<?= e(route('register')) ?>">Create an account</a>
    </div>
<?php else: ?>
    <div class="notice">You're signed in as <?= e($role) ?>. Use a customer account to reserve food.</div>
<?php endif ?>

<?php if ($role === 'customer'): ?>
<?php $this->start('scripts') ?>
<script>
(function () {
    var q = 1, max = <?= $max ?>, price = <?= json_encode((float) $listing->price) ?>;
    var show = document.getElementById('qty-show'), input = document.getElementById('quantity'), btn = document.getElementById('reserve-btn');
    document.querySelectorAll('[data-step]').forEach(function (b) {
        b.addEventListener('click', function () {
            q = Math.min(max, Math.max(1, q + Number(b.dataset.step)));
            show.textContent = q; input.value = q;
            btn.textContent = 'Reserve · ₱' + Math.round(price * q).toLocaleString('en-PH');
        });
    });
})();
</script>
<?php $this->end() ?>
<?php endif ?>
