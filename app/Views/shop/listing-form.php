<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]);
/** @var ?\App\Models\Listing $listing */
use App\Models\Listing;
$v = fn (string $key, $default = '') => old($key, $listing ? (string) $listing->$key : $default);
$action = $listing ? route('shop.listings.update', ['id' => $listing->id]) : route('shop.listings.store');
$err = function (string $field): string {
    $msg = errors($field);
    return $msg ? '<p class="err">' . e($msg) . '</p>' : '';
};
?>
<div class="sheethead">
    <h1><?= $listing ? 'Edit listing' : 'Post food' ?></h1>
    <a class="close" href="<?= e(route('shop.home')) ?>" aria-label="Back to my shop">×</a>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="name">Food name</label>
        <input id="name" name="name" value="<?= e($v('name')) ?>" placeholder="Pandesal, 10 pcs" required>
        <?= $err('name') ?>
    </div>
    <div class="grid2">
        <div class="field">
            <label for="category">Food type</label>
            <select id="category" name="category">
                <?php foreach (Listing::CATEGORIES as $key => $cat): ?>
                    <option value="<?= e($key) ?>" <?= $v('category', 'bread') === $key ? 'selected' : '' ?>><?= e($cat['label']) ?></option>
                <?php endforeach ?>
            </select>
            <?= $err('category') ?>
        </div>
        <div class="field">
            <label for="quantity">How many left?</label>
            <input id="quantity" name="quantity" type="number" min="0" max="999" value="<?= e($v('quantity', '5')) ?>" required>
            <?= $err('quantity') ?>
        </div>
        <div class="field">
            <label for="original_price">Original price (₱)</label>
            <input id="original_price" name="original_price" type="number" step="0.01" min="1" value="<?= e($v('original_price')) ?>" required>
            <?= $err('original_price') ?>
        </div>
        <div class="field">
            <label for="price">Replate price (₱)</label>
            <input id="price" name="price" type="number" step="0.01" min="1" value="<?= e($v('price')) ?>" required>
            <?= $err('price') ?>
        </div>
        <div class="field">
            <label for="best_before">Best before</label>
            <input id="best_before" name="best_before" type="date" value="<?= e($v('best_before', date('Y-m-d'))) ?>" required>
            <?= $err('best_before') ?>
        </div>
        <div class="field">
            <label for="pickup_window">Pickup time</label>
            <input id="pickup_window" name="pickup_window" value="<?= e($v('pickup_window', '6:00–8:00 PM')) ?>" required>
            <?= $err('pickup_window') ?>
        </div>
    </div>
    <div class="field">
        <label for="allergens">Allergens</label>
        <input id="allergens" name="allergens" value="<?= e($v('allergens')) ?>" placeholder="Wheat, egg, milk">
        <?= $err('allergens') ?>
    </div>
    <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" placeholder="Baked this morning. Still soft!"><?= e($v('description')) ?></textarea>
        <?= $err('description') ?>
    </div>
    <div class="field">
        <label for="photo">Photo</label>
        <div class="photopick">
            <div class="prev" id="photo-prev"><?php if ($listing && $listing->photo): ?><img src="<?= e(url($listing->photo)) ?>" alt=""><?php else: ?><?= Listing::CATEGORIES['bread']['art'] ?><?php endif ?></div>
            <div>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
                <p class="hint">JPG, PNG or WebP, up to 3 MB<?= $listing && $listing->photo ? '. Leave empty to keep the current one.' : '.' ?></p>
            </div>
        </div>
        <?= $err('photo') ?>
    </div>
    <button class="btn" type="submit"><?= $listing ? 'Save changes' : 'Post food' ?></button>
</form>

<?php $this->start('scripts') ?>
<script>
document.getElementById('photo').addEventListener('change', function () {
    var f = this.files[0]; if (!f) return;
    var img = document.createElement('img'); img.src = URL.createObjectURL(f); img.alt = '';
    var box = document.getElementById('photo-prev'); box.innerHTML = ''; box.appendChild(img);
});
</script>
<?php $this->end() ?>
