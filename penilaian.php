<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/lib/engine.php';
$cfg = appConfig();
$db  = pdoConnect();

$kriteria = getKriteria($db);
$kodeToId = []; foreach ($kriteria as $kode => $k) { $kodeToId[$kode] = (int)$k['id']; }
$pakar = getPakar($db);
$alts  = $db->query("SELECT a.id,a.kode,a.nama FROM tb_alternatif a JOIN mart_vendor_kriteria m ON m.supplier_name=a.nama ORDER BY m.jumlah_award DESC,a.id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAlt = (int)($_POST['id_alt'] ?? 0);
    $del = $db->prepare("DELETE FROM tb_penilaian WHERE id_alt=? AND id_krit=? AND id_pakar=?");
    $ins = $db->prepare("INSERT INTO tb_penilaian(id_alt,id_krit,id_pakar,nilai_label,nilai_index) VALUES(?,?,?,?,?)");
    foreach ($pakar as $pk) {
        $pid = (int)$pk['id'];
        foreach (['C2', 'C3'] as $kode) {
            $field = 'n_' . $kode . '_' . $pid;
            if (!isset($_POST[$field])) continue;
            $val = max(1, min(7, (int)$_POST[$field]));
            $kid = $kodeToId[$kode];
            $del->execute([$idAlt, $kid, $pid]);
            $ins->execute([$idAlt, $kid, $pid, skalaKode($val), $val]);
        }
    }
    $eng = runEngine($db, $cfg);
    persistHasil($db, $eng);
    header('Location: penilaian.php?alt=' . $idAlt . '&ok=1');
    exit;
}

$selId = (int)($_GET['alt'] ?? ($alts[0]['id'] ?? 0));
$eng = runEngine($db, $cfg);
$engRow = null;
foreach (($eng['rows'] ?? []) as $r) { if ((int)$r['alt']['id'] === $selId) { $engRow = $r; break; } }

$PAGE = 'penilaian.php'; $TITLE = 'Penilaian';
require __DIR__ . '/templates/header.php';
?>
<div class="page-title">
  <h3>Penilaian Pakar</h3>
  <p class="lead-small">C1 harga bersifat objektif dari data kontrak sehingga tidak diedit manual. Pakar hanya dapat menyesuaikan C2 dan C3 jika ada informasi kualitatif tambahan. Setelah disimpan, ranking dihitung ulang otomatis.</p>
</div>
<?php if (isset($_GET['ok'])): ?><div class="alert alert-success">Penilaian tersimpan dan hasil SPK telah dihitung ulang.</div><?php endif; ?>

<form method="get" class="form-inline mb-3">
  <label class="mr-2"><b>Pilih vendor</b></label>
  <select name="alt" class="form-control mr-2" onchange="this.form.submit()">
    <?php foreach ($alts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id']===$selId?'selected':'' ?>><?= htmlspecialchars($a['kode'].' - '.$a['nama']) ?></option><?php endforeach; ?>
  </select>
</form>

<?php if (!$engRow): ?>
  <div class="alert alert-warning">Vendor tidak ditemukan atau data masih kosong. Jalankan Sync terlebih dahulu.</div>
<?php else: $base = $engRow['skalaBase']; ?>
  <div class="card mb-3"><div class="card-header">Baseline Data</div><div class="card-body">
    <p class="mb-2"><b><?= htmlspecialchars($engRow['alt']['nama']) ?></b></p>
    <span class="mr-3">C1 Harga: <?= badgeSkala($base['C1']) ?></span>
    <span class="mr-3">C2 Reputasi: <?= badgeSkala($base['C2']) ?></span>
    <span>C3 Keandalan: <?= badgeSkala($base['C3']) ?></span>
    <p class="small-muted mb-0 mt-2">Baseline berasal dari hasil normalisasi SAW. Jika pakar tidak mengubah nilai, sistem memakai baseline ini.</p>
  </div></div>
  <form method="post">
    <input type="hidden" name="id_alt" value="<?= $selId ?>">
    <div class="card"><div class="card-header">Override C2 dan C3 per Pakar</div><div class="table-responsive">
      <table class="table table-sm mb-0"><thead><tr><th>Pakar</th><th>C2 - Kualitas/Reputasi</th><th>C3 - Layanan/Keandalan</th></tr></thead><tbody>
      <?php foreach ($pakar as $pk): $pid=(int)$pk['id'];
            $c2 = $engRow['indiv'][$pid]['ratings']['C2'] ?? $base['C2'];
            $c3 = $engRow['indiv'][$pid]['ratings']['C3'] ?? $base['C3']; ?>
        <tr>
          <td><b><?= htmlspecialchars($pk['kode']) ?></b><br><span class="small-muted"><?= htmlspecialchars($pk['nama']) ?></span></td>
          <td><?= selSkala('n_C2_'.$pid, (int)$c2) ?></td>
          <td><?= selSkala('n_C3_'.$pid, (int)$c3) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div><div class="card-footer"><button class="btn btn-primary" type="submit">Simpan dan Hitung Ulang</button></div></div>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/templates/footer.php'; ?>
