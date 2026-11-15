<?php // Leaflet 1.9.4 is bundled in public/assets/vendor so the map works without a CDN. ?>
<link rel="stylesheet" href="<?= e(asset('vendor/leaflet/leaflet.css')) ?>">
<script src="<?= e(asset('vendor/leaflet/leaflet.js')) ?>"></script>
<script>
    // Replate pins: a teardrop showing how many listings the shop has (map tiles © OpenStreetMap contributors).
    window.replatePin = function (count, color) {
        return L.divIcon({ className: '', html: '<div class="pin" style="background:' + color + '"><b>' + count + '</b></div>',
            iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -32] });
    };
    window.replateTiles = function (map) {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
    };
</script>
