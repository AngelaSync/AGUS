<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]) ?>
<h1>Sign in</h1>
<p class="sub">Welcome back! Sign in to reserve food or manage your shop.</p>

<form method="post" action="<?= e(url('/login')) ?>" class="panel" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= e(old('email')) ?>" required>
        <?php if ($err = errors('email')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <?php if ($err = errors('password')): ?><p class="err"><?= e($err) ?></p><?php endif ?>
    </div>
    <button class="btn" type="submit">Sign in</button>
</form>
<p class="small" style="text-align:center">No account yet? <a href="<?= e(route('register')) ?>">Create one</a></p>
<?php if (config('app.debug')): ?>
    <div class="notice lav">Demo accounts (password <strong>password123</strong>): customer juan@example.com · shop rosa@example.com · admin admin@example.com</div>
<?php endif ?>
