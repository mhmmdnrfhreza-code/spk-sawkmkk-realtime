<?php
require_once __DIR__ . '/config/koneksi.php';
$PAGE = 'data_master.php'; $TITLE = 'Data';
$db = pdoConnect();
$skala    = $db->query("SELECT * FROM tb_skala ORDER BY index_skala")->fetchAll();
$kriteria = $db->query("SELECT * FROM tb_kriteria ORDER BY kode")->fetchAll();
$pakar    = $db->query("SELECT * FROM tb_pakar ORDER BY id")->fetchAll();
$vendor   = $db->query("SELECT a.kode,a.nama,a.sumber,m.median_harga,m.jumlah_award,m.rasio_selesai
                        FROM tb_alternatif a LEFT JOIN mart_vendor_kriteria m ON m.supplier_name=a.nama
                        ORDER BY m.jumlah_award DESC, a.id")->fetchAll();
$cur = appConfig()['currency'];
$sampleKontrak = $db->query("SELECT ocid,supplier_name,amount,currency,tender_status,contract_status,award_date,category FROM stg_kontrak ORDER BY award_date DESC, id DESC LIMIT 12")->fetchAll();
$sampleRaw     = $db->query("SELECT ocid,source,fetched_at,payload FROM raw_ocds ORDER BY id DESC LIMIT 4")->fetchAll();
require __DIR__ . '/templates/header.php';
?>
<div class="page-title">
  <h3>Data Master & Kandidat</h3>
  <p class="lead-small">Halaman ini memisahkan data master metode (skala, kriteria, pakar) dan kandidat vendor hasil agregasi OCDS. Data kandidat bersifat read-only karena berasal dari proses sinkronisasi.</p>
</div>
<div class="row">
  <div class="col-lg-6 mb-3"><div class="card"><div class="card-header">Kriteria dan Bobot</div><div class="table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>Kode</th><th>Kriteria</th><th>Tipe</th><th>Bobot</th></tr></thead><tbody>
    <?php foreach ($kriteria as $k): ?><tr><td><b><?= $k['kode'] ?></b></td><td><?= htmlspecialchars($k['nama']) ?></td>
      <td><span class="badge-soft"><?= htmlspecialchars($k['tipe']) ?></span></td><td><?= badgeSkala((int)$k['bobot_index']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
  <div class="col-lg-6 mb-3"><div class="card"><div class="card-header">Pakar</div><div class="table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>Kode</th><th>Nama</th><th>Peran</th></tr></thead><tbody>
    <?php foreach ($pakar as $p): ?><tr><td><b><?= $p['kode'] ?></b></td><td><?= htmlspecialchars($p['nama']) ?></td><td class="small-muted"><?= htmlspecialchars($p['jabatan']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
</div>
<div class="card mb-3"><div class="card-header">Skala Kualitatif</div><div class="card-body">
  <p class="small-muted">Skala digunakan oleh KMKK. Warna hanya dipakai sebagai indikator penting agar pembacaan predikat cepat.</p>
  <?php foreach ($skala as $s): ?><span class="mr-3 mb-2 d-inline-block"><?= badgeSkala((int)$s['index_skala']) ?> <span class="small-muted"><?= htmlspecialchars($s['label']) ?></span></span><?php endforeach; ?>
</div></div>
<div class="card"><div class="card-header">Vendor Kandidat SPK</div><div class="table-responsive">
  <table class="table table-sm mb-0"><thead><tr><th>Kode</th><th>Vendor</th><th>Sumber</th><th>C1 Median Harga</th><th>C2 Award</th><th>C3 Rasio Selesai</th></tr></thead><tbody>
  <?php if (!$vendor): ?><tr><td colspan="6" class="p-3 text-muted">Belum ada vendor. Jalankan Sync.</td></tr><?php endif; ?>
  <?php foreach ($vendor as $v): ?><tr>
    <td><b><?= htmlspecialchars($v['kode']) ?></b></td><td><?= htmlspecialchars($v['nama']) ?></td><td><span class="badge-soft"><?= htmlspecialchars($v['sumber']) ?></span></td>
    <td class="mono"><?= $v['median_harga']!==null?fmtMoney($v['median_harga'],$cur):'-' ?></td>
    <td class="mono"><?= $v['jumlah_award']!==null?number_format((int)$v['jumlah_award']):'-' ?></td>
    <td class="mono"><?= $v['rasio_selesai']!==null?number_format((float)$v['rasio_selesai'],2):'-' ?></td>
  </tr><?php endforeach; ?>
  </tbody></table>
</div><div class="card-footer small-muted">Jumlah kandidat mengikuti konfigurasi <span class="mono">top_n</span>. Semua vendor agregat tetap tersimpan di mart.</div></div>

<div class="card mt-3"><div class="card-header">Sampel Data OCDS (Bukti Sumber)</div><div class="card-body">
  <p class="small-muted">Cuplikan data nyata hasil sinkronisasi sebagai bukti sumber dan jejak audit. <b>Staging</b> adalah hasil transformasi rilis OCDS menjadi baris kontrak per supplier; <b>Raw</b> adalah rilis OCDS mentah apa adanya dari API.</p>
  <div class="mb-2"><b>A. Staging kontrak (stg_kontrak)</b> &mdash; 12 baris terbaru</div>
  <div class="table-responsive mb-3"><table class="table table-sm mb-0">
    <thead><tr><th>OCID</th><th>Supplier</th><th>Nilai</th><th>Tender</th><th>Kontrak</th><th>Tgl Award</th><th>Kategori/CPV</th></tr></thead><tbody>
    <?php if (!$sampleKontrak): ?><tr><td colspan="7" class="p-3 text-muted">Belum ada data. Jalankan Sync.</td></tr><?php endif; ?>
    <?php foreach ($sampleKontrak as $s): ?><tr>
      <td class="mono small"><?= htmlspecialchars($s['ocid']) ?></td>
      <td><?= htmlspecialchars($s['supplier_name']) ?></td>
      <td class="mono"><?= $s['amount']!==null?fmtMoney($s['amount'],$s['currency']?:$cur):'-' ?></td>
      <td><span class="badge-soft"><?= htmlspecialchars($s['tender_status'] ?? '-') ?></span></td>
      <td><span class="badge-soft"><?= htmlspecialchars($s['contract_status'] ?? '-') ?></span></td>
      <td class="mono small"><?= htmlspecialchars($s['award_date'] ?? '-') ?></td>
      <td class="small-muted"><?= htmlspecialchars($s['category'] ?? '-') ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  <div class="mb-2"><b>B. Rilis OCDS mentah (raw_ocds)</b> &mdash; 4 rilis terbaru</div>
  <?php foreach ($sampleRaw as $rw): ?>
    <details class="mb-2">
      <summary class="mono small"><?= htmlspecialchars($rw['ocid']) ?> &middot; <span class="small-muted">source=<?= htmlspecialchars($rw['source']) ?> &middot; fetched=<?= htmlspecialchars($rw['fetched_at']) ?></span></summary>
      <pre class="mono small mb-0" style="white-space:pre-wrap;background:#f6f6f2;border:1px solid var(--line);border-radius:8px;padding:.75rem;margin-top:.5rem;max-height:240px;overflow:auto"><?= htmlspecialchars(mb_substr((string)$rw['payload'],0,1200)) ?><?= mb_strlen((string)$rw['payload'])>1200?"\n... (dipotong)":'' ?></pre>
    </details>
  <?php endforeach; ?>
  <?php if (!$sampleRaw): ?><p class="text-muted mb-0">Belum ada rilis mentah.</p><?php endif; ?>
</div><div class="card-footer small-muted">Data publik ber-lisensi Open Government Licence v3.0. OCID = Open Contracting ID, identitas unik tiap proses kontrak.</div></div>
<?php require __DIR__ . '/templates/footer.php'; ?>
