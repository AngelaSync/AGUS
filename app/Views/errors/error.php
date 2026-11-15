<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => 'Error ' . $status]) ?>
<div class="panel" style="text-align:center;margin-top:16px">
    <p class="error-code"><?= (int) $status ?></p>
    <h1 style="font-size:22px"><?= e($message) ?></h1>
    <a class="btn" href="<?= e(url('/')) ?>" style="margin-top:8px">Back to Browse</a>
    <?php if (!empty($exception)): ?>
        <details class="debug" open>
            <summary><?= e($exception::class) ?> in <?= e(basename($exception->getFile())) ?>:<?= (int) $exception->getLine() ?></summary>
            <pre><?= e($exception->getTraceAsString()) ?></pre>
            <p class="small">You see this because APP_DEBUG=true. Turn it off on a live server.</p>
        </details>
    <?php endif ?>
</div>
