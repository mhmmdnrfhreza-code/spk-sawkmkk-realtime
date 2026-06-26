<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/ingestion/sync_all.php';
$cfg = appConfig();
$db  = pdoConnect();
$result = null;
$snapSaved = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $forceFallback = isset($_POST['fallback']);
    $result = runSync($db, $cfg, $forceFallback);
    // Simpan jadi snapshot hanya bila sumber LIVE & ada data (tidak menimpa saat fallback).
    if (!empty($result['ok']) && isset($_POST['save_snapshot']) && !$forceFallback
        && (int)($result['fetch']['fetched'] ?? 0) > 0
        && stripos((string)($result['fetch']['source'] ?? ''), 'snapshot') === false) {
        $snapSaved = saveSnapshot($db);
    }
}
$logs = $db->query("SELECT * FROM tb_sync_log ORDER BY id DESC LIMIT 15")->fetchAll();
$fresh = $db->query("SELECT MAX(last_sync) m FROM mart_vendor_kriteria")->fetch()['m'];
$vendorMart = (int)$db->query("SELECT COUNT(*) c FROM mart_vendor_kriteria")->fetch()['c'];
$PAGE = 'sync.php'; $TITLE = 'Sync';
require __DIR__ . '/templates/header.php';
?>
<div class="page-title">
  <h3>Sinkronisasi Data OCDS</h3>
  <p class="lead-small">Menu ini menarik data kontrak publik 5 tahun terakhir, mengubahnya menjadi data vendor, lalu menghitung ulang hasil SAW-KMKK. Mode live memakai Contracts Finder sebagai sumber utama dan Find a Tender sebagai pelengkap.</p>
</div>
<div class="row">
  <div class="col-lg-5 mb-3"><div class="card"><div class="card-header">Kontrol Sinkronisasi</div><div class="card-body">
    <p class="small-muted mb-2"><b>Sumber live:</b> Contracts Finder + Find a Tender<br><b>Jendela:</b> <?= (int)$cfg['ocds']['years_back'] ?> tahun terakhir<br><b>Filter:</b> CPV <?= implode(', ', $cfg['ocds']['cpv_prefixes']) ?>xxxxxx + kata kunci kesehatan</p>
    <p class="small-muted mb-3"><b>Data freshness:</b> <?= htmlspecialchars($fresh ?? '-') ?><br><b>Vendor agregat saat ini:</b> <?= number_format($vendorMart) ?></p>
    <form method="post">
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="save_snapshot" id="sv">
        <label class="form-check-label" for="sv">Save (Live > Snapshot)</label>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="fallback" id="fb">
        <label class="form-check-label" for="fb">Snapshot (Demo/Offline)</label>
      </div>
      <button class="btn btn-primary" type="submit">Sync Data</button>
    </form>
    <?php if ($snapSaved !== null): ?>
      <div class="alert alert-info mt-3 mb-0 small">
        Snapshot Tersimpan: <b><?= number_format((int)$snapSaved['count']) ?></b> release
        (<?= number_format((int)$snapSaved['bytes'] / 1024, 1) ?> KB) ke <span class="mono">data/snapshot_ocds.json</span>.
      </div>
    <?php endif; ?>
    <p class="small-muted mt-3 mb-0">Jika API lambat/tidak ada respon, sistem tetap dapat berjalan memakai snapshot.</p>
  </div></div></div>
  <div class="col-lg-7 mb-3"><div class="card"><div class="card-header">Ringkasan Hasil Sync</div><div class="card-body">
    <?php if ($result === null): ?>
      <p class="text-muted mb-0">Tekan <b>Sync Data</b> untuk menarik data terbaru dan menghitung ulang peringkat.</p>
    <?php elseif ($result['ok']): ?>
      <div class="alert alert-success mb-3">Sinkronisasi selesai dan hasil SPK telah dihitung ulang.</div>
      <div class="row">
        <div class="col-6 col-md-3 mb-2"><div class="stat"><div class="label">Source</div><h2 style="font-size:1rem"><?= htmlspecialchars($result['fetch']['source']) ?></h2></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="stat"><div class="label">Releases</div><h2><?= number_format((int)$result['fetch']['fetched']) ?></h2></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="stat"><div class="label">Kontrak</div><h2><?= number_format((int)$result['kontrak']) ?></h2></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="stat"><div class="label">Kandidat</div><h2><?= number_format((int)$result['vendor']) ?></h2></div></div>
      </div>
      <p class="small-muted mb-2 mono"><?= htmlspecialchars($result['note']) ?></p>
      <a href="perhitungan.php" class="btn btn-sm btn-outline-primary">Lihat Perhitungan</a>
    <?php else: ?>
      <div class="alert alert-danger">Gagal: <?= htmlspecialchars($result['error']) ?></div>
    <?php endif; ?>
  </div></div></div>
</div>
<div class="card"><div class="card-header">Log Sinkronisasi</div><div class="table-responsive">
  <table class="table table-sm mb-0"><thead><tr><th>#</th><th>Mulai</th><th>Selesai</th><th>Record</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?><tr>
    <td><?= (int)$l['id'] ?></td><td class="small"><?= htmlspecialchars($l['started_at']) ?></td>
    <td class="small"><?= htmlspecialchars($l['finished_at'] ?? '-') ?></td><td><?= number_format((int)$l['records_fetched']) ?></td>
    <td><span class="badge-soft"><?= htmlspecialchars($l['status']) ?></span></td>
    <td class="small mono"><?= htmlspecialchars($l['note'] ?? '') ?></td>
  </tr><?php endforeach; ?>
  </tbody></table>
</div></div>
<?php require __DIR__ . '/templates/footer.php'; ?>
