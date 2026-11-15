<?php /** @var \Agus\View\View $this */ $this->layout('layouts/main', ['title' => $title]) ?>
<h1>How it works</h1>
<p class="sub">Replate is a web app where bakeries and carinderias in Angeles City sell their leftover food at a discount. It runs on AGUS, a PHP MVC framework I wrote from scratch: no Laravel and no Composer packages.</p>

<h2>For customers</h2>
<div class="panel">
    <p>1. Browse tonight's food; search and filter by type, price and distance.</p>
    <p>2. Reserve it and get a pickup code like <strong>RP-7K4Q</strong>.</p>
    <p>3. Go to the shop, check the food, show the code and pay at the counter.</p>
</div>

<h2>How a request flows in AGUS</h2>
<div class="panel">
    <p>1. <strong>public/index.php</strong> receives every request.</p>
    <p>2. <strong>Request::capture()</strong> wraps the URL, form data and headers.</p>
    <p>3. Global middleware runs: logging, security headers, session, CSRF.</p>
    <p>4. The <strong>Router</strong> finds the matching route.</p>
    <p>5. Route middleware runs: auth, role:shop, role:admin, throttle:5,1.</p>
    <p>6. The controller returns a <strong>Response</strong>, and <strong>send()</strong> outputs it.</p>
</div>
