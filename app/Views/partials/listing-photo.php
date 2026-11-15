<?php /** @var \App\Models\Listing $listing */ $size = $size ?? ''; ?>
<div class="art <?= e($size) ?> tone-<?= e($listing->tone()) ?>">
    <?php if ($listing->photo): ?>
        <img src="<?= e(url($listing->photo)) ?>" alt="<?= e($listing->name) ?>" loading="lazy">
    <?php else: ?>
        <?= $listing->art() ?>
    <?php endif ?>
</div>
