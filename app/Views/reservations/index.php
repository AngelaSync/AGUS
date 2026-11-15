<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]);
$labels = ['reserved' => ['Ready for pickup', 't-warn'], 'picked_up' => ['Picked up', 't-ok'], 'cancelled' => ['Cancelled', 't-bad']];
?>
<h1>Reservations</h1>
<p class="sub">Show your code at the counter, check the food, then pay there.</p>

<?php if (!$reservations): ?>
    <div class="panel empty">
        <p><strong>No reservations yet.</strong></p>
        <p>Find something tasty nearby and reserve it before it's gone.</p>
        <a class="btn" href="<?= e(url('/')) ?>">Browse food</a>
    </div>
<?php else: ?>
    <div class="stack">
        <?php foreach ($reservations as $r): [$label, $tone] = $labels[$r['status']] ?? [$r['status'], 't-plain']; ?>
            <div class="panel stack" style="gap:8px">
                <div class="row between">
                    <span class="tag <?= $tone ?>"><?= e($label) ?></span>
                    <span class="small"><?= e(date('M j, g:i A', strtotime((string) $r['created_at']))) ?></span>
                </div>
                <?php if ($r['status'] === 'reserved'): ?><div class="code"><?= e($r['pickup_code']) ?></div><?php endif ?>
                <div>
                    <h3><?= (int) $r['quantity'] ?> × <?= e($r['listing_name']) ?></h3>
                    <div class="small"><?= e($r['shop_name']) ?> · <?= e($r['shop_address']) ?></div>
                    <div class="small">Pickup <?= e($r['pickup_window']) ?><?= $r['status'] !== 'reserved' ? ' · Code ' . e($r['pickup_code']) : '' ?></div>
                </div>
                <div class="row between">
                    <span class="price"><?= e(money($r['total'], 0)) ?> <span class="small">at the counter</span></span>
                    <?php if ($r['status'] === 'reserved'): ?>
                        <form method="post" action="<?= e(route('reservations.cancel', ['id' => $r['id']])) ?>" class="inline">
                            <?= csrf_field() ?>
                            <button class="btn danger small" type="submit">Cancel</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
