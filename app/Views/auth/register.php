<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]) ?>
<h1>Create account</h1>
<p class="sub">Reserve good food from shops near you, for less.</p>

<form method="post" action="<?= e(url('/register')) ?>" class="panel" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="name">Name</label>
        <input id="name" name="name" autocomplete="name" value="<?= e(old('name')) ?>" required>
        <?php if ($err = errors('name')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= e(old('email')) ?>" required>
        <?php if ($err = errors('email')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
        <?php if ($err = errors('password')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    </div>
    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <button class="btn" type="submit">Create account</button>
</form>
<p class="small" style="text-align:center">Already have an account? <a href="<?= e(route('login')) ?>">Sign in</a></p>
