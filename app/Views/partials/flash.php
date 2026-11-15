<?php if ($msg = flash('success')): ?>
    <div class="notice ok flash" role="status"><?= e($msg) ?></div>
<?php endif ?>
<?php if ($msg = flash('error')): ?>
    <div class="notice bad flash" role="alert"><?= e($msg) ?></div>
<?php endif ?>
