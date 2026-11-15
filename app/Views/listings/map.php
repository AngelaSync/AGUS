<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]) ?>
<h1>Shops near you</h1>
<p class="sub">Tap a pin to see what a shop has left. Numbers show live listings.</p>

<div class="map">
    <div id="lmap" role="region" aria-label="Map of verified shops"></div>
    <div class="legend">
        <span><i style="background:#DE4525"></i>Has food now</span>
        <span><i style="background:#7A5A4C"></i>Nothing listed</span>
    </div>
</div>
<p class="small" style="margin-top:10px">Pins come from each shop's address, turned into coordinates by the OpenStreetMap geocoding API.</p>

<?php $this->start('scripts') ?>
<?= $this->insert('partials/leaflet') ?>
<script>
    var pins = <?= json_encode($pins, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var map = L.map('lmap', { zoomControl: true }).setView([15.1450, 120.5887], 14);
    replateTiles(map);
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    var bounds = [];
    pins.forEach(function (p) {
        var html = '<strong>' + esc(p.name) + '</strong><br>' + esc(p.address) + '<br>'
            + (p.listings > 0 ? '<a href="' + p.url + '">' + p.listings + ' listing' + (p.listings === 1 ? '' : 's') + ' now →</a>' : '<span>Nothing listed right now</span>');
        L.marker([p.lat, p.lon], { icon: replatePin(p.listings, p.listings > 0 ? '#DE4525' : '#7A5A4C') }).addTo(map).bindPopup(html);
        bounds.push([p.lat, p.lon]);
    });
    if (bounds.length > 1) { map.fitBounds(bounds, { padding: [40, 40] }); }
</script>
<?php $this->end() ?>
