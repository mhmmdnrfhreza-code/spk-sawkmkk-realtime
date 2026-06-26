<?php
require_once __DIR__ . '/config/koneksi.php';
$PAGE = 'index.php'; $TITLE = 'Dashboard';
$db = pdoConnect();
$cfg = appConfig();
$counts = [
    'vendor_mart' => (int)$db->query("SELECT COUNT(*) c FROM mart_vendor_kriteria")->fetch()['c'],
    'kontrak'     => (int)$db->query("SELECT COUNT(*) c FROM stg_kontrak")->fetch()['c'],
    'raw'         => (int)$db->query("SELECT COUNT(*) c FROM raw_ocds")->fetch()['c'],
    'alt'         => (int)$db->query("SELECT COUNT(*) c FROM tb_alternatif")->fetch()['c'],
];
$last  = $db->query("SELECT * FROM tb_sync_log ORDER BY id DESC LIMIT 1")->fetch();
$fresh = $db->query("SELECT MAX(last_sync) m FROM mart_vendor_kriteria")->fetch()['m'];
$top   = $db->query("SELECT * FROM tb_hasil ORDER BY ranking LIMIT 10")->fetchAll();
require __DIR__ . '/templates/header.php';
?>
<div class="page-title d-flex flex-column flex-md-row justify-content-between align-items-md-end">
  <div>
    <h3>Dashboard Seleksi Vendor</h3>
    <p class="lead-small">Ringkasan data kontrak OCDS, kandidat vendor, dan hasil akhir metode SAW-KMKK. Angka kandidat mengikuti konfigurasi <span class="mono">top_n</span>; seluruh vendor hasil agregasi tetap tersimpan pada mart.</p>
  </div>
  <a href="sync.php" class="btn btn-primary btn-sm mt-3 mt-md-0">Sinkronkan Data</a>
</div>

<?php if ($counts['vendor_mart'] === 0): ?>
  <div class="alert alert-warning">Data belum tersedia. Jalankan <a href="install.php">install.php</a> atau buka <a href="sync.php">Sync</a> lalu tekan <b>Sync Now</b>.</div>
<?php endif; ?>

<div class="row">
  <div class="col-md-3 mb-3"><div class="stat"><div class="label">Rilis OCDS</div><h2><?= number_format($counts['raw']) ?></h2><div class="hint">Data mentah tersimpan</div></div></div>
  <div class="col-md-3 mb-3"><div class="stat"><div class="label">Baris Kontrak</div><h2><?= number_format($counts['kontrak']) ?></h2><div class="hint">Hasil transformasi</div></div></div>
  <div class="col-md-3 mb-3"><div class="stat"><div class="label">Vendor Agregat</div><h2><?= number_format($counts['vendor_mart']) ?></h2><div class="hint">Seluruh vendor di mart</div></div></div>
  <div class="col-md-3 mb-3"><div class="stat"><div class="label">Kandidat SPK</div><h2><?= number_format($counts['alt']) ?></h2><div class="hint">Shortlist top-N</div></div></div>
</div>

<div class="row">
  <div class="col-lg-8 mb-3">
    <div class="card"><div class="card-header">Peringkat Akhir Vendor</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>#</th><th>Vendor</th><th>Predikat KMKK-OWA</th><th>Skor SAW</th></tr></thead>
        <tbody>
        <?php if (!$top): ?><tr><td colspan="4" class="text-muted p-3">Belum ada hasil.</td></tr><?php endif; ?>
        <?php foreach ($top as $r): ?>
          <tr>
            <td class="rank-one"><?= (int)$r['ranking'] ?></td>
            <td><?= htmlspecialchars($r['supplier_name']) ?></td>
            <td><?= badgeSkala((int)$r['p_index']) ?></td>
            <td class="mono"><?= number_format((float)$r['saw_score'], 4) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card-footer small-muted">Urutan utama memakai predikat kualitatif KMKK-OWA; skor SAW dipakai sebagai pembeda jika predikat sama.</div>
    </div>
  </div>
  <div class="col-lg-4 mb-3">
    <div class="card mb-3"><div class="card-header">Status Data</div><div class="card-body">
      <?php if ($last): ?>
        <div class="mb-2"><span class="badge-soft"><?= htmlspecialchars($last['status']) ?></span></div>
        <p class="mb-1"><b>Catatan:</b></p>
        <p class="small-muted mono mb-2"><?= htmlspecialchars($last['note'] ?? '-') ?></p>
        <p class="small-muted mb-0">Data freshness: <?= htmlspecialchars($fresh ?? '-') ?></p>
      <?php else: ?><p class="text-muted mb-0">Belum pernah sinkron.</p><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-header">Alur Sistem</div><div class="card-body">
      <ol class="small-muted pl-3 mb-0">
        <li>API OCDS menarik rilis kontrak 5 tahun terakhir.</li>
        <li>Data diubah menjadi baris kontrak per supplier.</li>
        <li>Vendor diagregasi menjadi C1, C2, C3.</li>
        <li>SAW memetakan angka ke skala S1-S7.</li>
        <li>KMKK dan OWA menghasilkan predikat akhir.</li>
      </ol>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
