<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]) ?>
<h1>Sell your surplus</h1>
<p class="sub">Tell us about your shop. We'll put it on the map, and an admin verifies it before your food goes live.</p>

<form method="post" action="<?= e(url('/sell')) ?>" class="panel" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="name">Shop name</label>
        <input id="name" name="name" value="<?= e(old('name')) ?>" placeholder="Panaderia ni Aling Rosa" required>
        <?php if ($err = errors('name')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    </div>
    <div class="field">
        <label for="address">Address</label>
        <input id="address" name="address" value="<?= e(old('address')) ?>" placeholder="Sto. Rosario St, Angeles City, Pampanga" required>
        <p class="hint">Include the street, city and province so we can find it on the map.</p>
        <?php if ($err = errors('address')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    </div>
    <button class="btn" type="submit">Create my shop</button>
</form>
