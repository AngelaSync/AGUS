<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]);
use App\Models\Listing;
use App\Services\Ui;
$queryWith = function (array $change) use ($filters): string {
    $params = array_filter(array_merge($filters, $change), fn ($v) => $v !== '' && $v !== null);
    return url('/') . ($params ? '?' . http_build_query($params) : '');
};
?>
<h1>Good food,<br>nearby, for less.</h1>
<p class="sub">Reserve leftovers from local shops. Check the food, then pay at the counter.</p>

<form method="get" action="<?= e(url('/')) ?>" id="filters">
    <label class="search">
        <?= Ui::icon('search') ?>
        <span class="sr-only">Search food</span>
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Pandesal, adobo, ube…" enterkeyhint="search">
    </label>
    <div class="chips" aria-label="Food type">
        <?php foreach (Listing::CATEGORIES as $key => $cat): ?>
            <a class="chip <?= $filters['category'] === $key ? 'on' : '' ?>" href="<?= e($queryWith(['category' => $filters['category'] === $key ? '' : $key])) ?>"><?= e($cat['label']) ?></a>
        <?php endforeach ?>
        <a class="chip <?= $filters['max_price'] === '50' ? 'on' : '' ?>" href="<?= e($queryWith(['max_price' => $filters['max_price'] === '50' ? '' : '50'])) ?>">Under ₱50</a>
    </div>
    <div class="filters">
        <select name="radius" aria-label="Distance" onchange="this.form.submit()">
            <option value="">Any distance</option>
            <?php foreach ([1, 2, 5, 10] as $km): ?>
                <option value="<?= $km ?>" <?= $filters['radius'] === (string) $km ? 'selected' : '' ?>>Within <?= $km ?> km</option>
            <?php endforeach ?>
        </select>
        <select name="sort" aria-label="Sort by" onchange="this.form.submit()">
            <option value="nearest" <?= $filters['sort'] === 'nearest' ? 'selected' : '' ?>>Nearest first</option>
            <option value="cheapest" <?= $filters['sort'] === 'cheapest' ? 'selected' : '' ?>>Cheapest first</option>
            <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest first</option>
        </select>
    </div>
    <?php foreach (['category', 'max_price', 'lat', 'lon'] as $keep): ?>
        <input type="hidden" name="<?= $keep ?>" id="f-<?= $keep ?>" value="<?= e($filters[$keep]) ?>">
    <?php endforeach ?>
</form>

<div class="meta">
    <span><?= count($listings) ?> result<?= count($listings) === 1 ? '' : 's' ?></span>
    <button class="link" type="button" id="use-location" title="<?= $usingMyLocation ? 'Distances are from your location' : 'Distances are from Angeles City center' ?>"><?= Ui::icon('map') ?><?= $usingMyLocation ? 'Near you' : 'Use my location' ?></button>
</div>

<?php if (!$listings): ?>
    <div class="panel empty">
        <p><strong>Nothing matches yet.</strong></p>
        <p>Try another food type, a higher price, or a wider distance. Shops post new food every evening.</p>
        <a class="btn alt" href="<?= e(url('/')) ?>">Show all food</a>
    </div>
<?php else: ?>
    <div class="stack">
        <?php foreach ($listings as $listing): ?>
            <a class="card" href="<?= e(route('listings.show', ['id' => $listing->id])) ?>">
                <?= $this->insert('partials/listing-photo', ['listing' => $listing]) ?>
                <div class="cbody">
                    <div class="row" style="gap:6px">
                        <?php if ($listing->discount() >= 50): ?><span class="tag t-boost"><?= $listing->discount() ?>% off</span><?php endif ?>
                        <span class="tag t-left"><?= (int) $listing->quantity ?> left</span>
                    </div>
                    <span class="name"><?= e($listing->name) ?></span>
                    <span class="shop"><?= e($listing->shop_name) ?> <span class="verified"><?= Ui::icon('check') ?></span><?php if ($listing->distance !== null): ?> · <?= $listing->distance < 0.1 ? 'nearby' : e(number_format($listing->distance, 1)) . ' km' ?><?php endif ?></span>
                    <span class="price"><?= e(money($listing->price, 0)) ?><s><?= e(money($listing->original_price, 0)) ?></s></span>
                    <span class="small">Pickup <?= e($listing->pickup_window) ?> · Best before <?= e(date('M j', strtotime((string) $listing->best_before))) ?></span>
                    <?php if ($listing->allergenList()): ?>
                        <span class="allergens"><?php foreach ($listing->allergenList() as $a): ?><span><?= e($a) ?></span><?php endforeach ?></span>
                    <?php endif ?>
                </div>
            </a>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php $this->start('scripts') ?>
<script>
document.getElementById('use-location').addEventListener('click', function () {
    var btn = this;
    if (!navigator.geolocation) { btn.textContent = 'Not supported'; return; }
    btn.textContent = 'Finding you…';
    navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById('f-lat').value = pos.coords.latitude.toFixed(5);
        document.getElementById('f-lon').value = pos.coords.longitude.toFixed(5);
        document.getElementById('filters').submit();
    }, function () { btn.textContent = 'Location blocked'; });
});
</script>
<?php $this->end() ?>
