<?php
require_once __DIR__ . '/config/koneksi.php';
$PAGE = 'about.php'; $TITLE = 'Tentang';
$cfg = appConfig();
require __DIR__ . '/templates/header.php';
?>
<div class="page-title">
  <h3>Tentang Sistem</h3>
  <p class="lead-small">Sistem ini dirancang sebagai SPK hibrida untuk membantu seleksi vendor alat kesehatan rumah sakit. Data kontrak publik dipakai sebagai dasar objektif, sedangkan SAW-KMKK dipakai sebagai model keputusan yang transparan dan dapat dijelaskan.</p>
</div>
<div class="card mb-3"><div class="card-header">Konsep Sistem</div><div class="card-body">
  <ul class="mb-0">
    <li><b>Data-driven layer:</b> ETL data kontrak publik OCDS dari Contracts Finder dan Find a Tender, jendela <?= (int)$cfg['ocds']['years_back'] ?> tahun terakhir, filter CPV kesehatan dan kata kunci medis.</li>
    <li><b>Model-driven layer:</b> SAW untuk normalisasi, KMKK Minimax untuk keputusan individu, dan OWA Maximin untuk keputusan kelompok pakar.</li>
    <li><b>Output:</b> shortlist vendor kandidat beserta predikat kualitatif S1-S7 dan skor SAW sebagai tie-break.</li>
  </ul>
</div></div>

<div class="row">
  <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header">Kriteria Keputusan</div><div class="card-body">
    <table class="table table-sm mb-0"><thead><tr><th>Kode</th><th>Makna</th><th>Data</th></tr></thead><tbody>
      <tr><td><b>C1</b></td><td>Harga / efisiensi biaya</td><td>Median nilai award per vendor (cost)</td></tr>
      <tr><td><b>C2</b></td><td>Reputasi / kualitas pasar</td><td>Frekuensi award (benefit)</td></tr>
      <tr><td><b>C3</b></td><td>Keandalan layanan</td><td>Rasio kontrak aktif/selesai (benefit)</td></tr>
    </tbody></table>
  </div></div></div>
  <div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-header">Teknologi</div><div class="card-body">
    <ul class="mb-0">
      <li>PHP 7.4+ dengan PDO prepared statements</li>
      <li>MySQL / MariaDB pada Laragon atau XAMPP</li>
      <li>Bootstrap 4 dan Chart.js 3</li>
      <li>cURL untuk ingestion API, snapshot lokal untuk fallback</li>
    </ul>
  </div></div></div>
</div>

<div class="card mb-3"><div class="card-header">Data OCDS yang Diambil</div><div class="card-body">
  <div class="row">
    <div class="col-lg-6 mb-3">
      <div class="mb-2"><b>Sumber &amp; cakupan</b></div>
      <ul class="small-muted mb-0">
        <li>Sumber: <?= htmlspecialchars(implode(', ', $cfg['ocds']['sources'])) ?></li>
        <li>Contracts Finder OCDS: <span class="mono"><?= htmlspecialchars($cfg['ocds']['cf_endpoint']) ?></span></li>
        <li>Find a Tender OCDS: <span class="mono"><?= htmlspecialchars($cfg['ocds']['endpoint']) ?></span></li>
        <li>Tahap diambil: <span class="mono"><?= htmlspecialchars($cfg['ocds']['cf_stages']) ?></span> (rilis award &mdash; sudah memuat supplier &amp; nilai)</li>
        <li>Jendela waktu: <?= (int)$cfg['ocds']['years_back'] ?> tahun terakhir (rolling)</li>
        <li>Filter CPV: prefiks <?= htmlspecialchars(implode(', ', $cfg['ocds']['cpv_prefixes'])) ?>xxxxxx (peralatan medis, farmasi, perawatan)</li>
      </ul>
    </div>
    <div class="col-lg-6 mb-3">
      <div class="mb-2"><b>Field OCDS &rarr; kriteria SPK</b></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Field OCDS</th><th>Dipakai untuk</th></tr></thead><tbody>
        <tr><td class="mono small">awards.suppliers.name</td><td>Identitas vendor (alternatif)</td></tr>
        <tr><td class="mono small">awards.value.amount / currency</td><td>C1 &mdash; median harga (cost)</td></tr>
        <tr><td class="mono small">awards[] (frekuensi)</td><td>C2 &mdash; jumlah award (benefit)</td></tr>
        <tr><td class="mono small">contracts.status / awards.status</td><td>C3 &mdash; rasio selesai (benefit)</td></tr>
        <tr><td class="mono small">awards.date / contractPeriod</td><td>Tren kontrak per tahun</td></tr>
        <tr><td class="mono small">tender.classification (CPV)</td><td>Filter relevansi kesehatan</td></tr>
        <tr><td class="mono small">ocid</td><td>Identitas unik &amp; jejak audit</td></tr>
      </tbody></table></div>
    </div>
  </div>
  <div class="mb-2"><b>Kata kunci kesehatan (recall bila CPV tidak 33xxxxxx):</b></div>
  <div><?php foreach ($cfg['ocds']['health_keywords'] as $kw): ?><span class="badge-soft mr-1 mb-1 d-inline-block"><?= htmlspecialchars($kw) ?></span><?php endforeach; ?></div>
</div></div>

<div class="card mb-3"><div class="card-header">Pipeline Data (ETL)</div><div class="card-body">
  <div class="flow d-flex flex-wrap align-items-center">
    <span class="flow-step">API OCDS</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">raw_ocds (rilis mentah)</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">stg_kontrak (transform)</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">mart_vendor_kriteria (agregasi C1-C3)</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step flow-end">Engine SAW-KMKK</span>
  </div>
  <p class="small-muted mb-0 mt-2">Cuplikan data nyata tiap lapisan dapat dilihat pada menu <a href="data_master.php">Data</a> bagian &ldquo;Sampel Data OCDS&rdquo;.</p>
</div></div>

<div class="card mb-3"><div class="card-header">Referensi Rumus</div><div class="card-body">
  <div class="formula-box mb-2"><b>SAW:</b> cost = min/x, benefit = x/max, skor = &Sigma;(r<sub>j</sub> &times; w<sub>j</sub>).</div>
  <div class="formula-box mb-2"><b>KMKK Minimax:</b> P<sup>k</sup> = Min<sub>j</sub>[Neg(W<sub>j</sub>) OR R<sub>j</sub>].</div>
  <div class="formula-box"><b>OWA Maximin:</b> P = Max<sub>j</sub>[Q(j) AND B<sub>j</sub>].</div>
  <ul class="small-muted mt-3 mb-0">
    <li>Yager, R. R. (1993). Non-numeric multi-criteria multi-person decision making.</li>
    <li>Yager, R. R. (1988). Ordered Weighted Averaging aggregation operators.</li>
    <li>Open Contracting Data Standard (OCDS) v1.1.</li>
  </ul>
</div></div>

<div class="card"><div class="card-header">Atribusi Data</div><div class="card-body">
  <p class="mb-1"><?= htmlspecialchars($cfg['attribution']) ?></p>
  <p class="small-muted mb-0">Snapshot lokal bersifat sintetis untuk demo/offline. Untuk analisis aktual, gunakan menu Sync saat komputer terhubung internet.</p>
</div></div>
<?php require __DIR__ . '/templates/footer.php'; ?>
