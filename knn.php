<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/lib/engine.php';
require_once __DIR__ . '/helpers/knn.php';
$cfg = appConfig();
$db  = pdoConnect();
$eng = runEngine($db, $cfg);
$PAGE = 'knn.php'; $TITLE = 'Data-Driven (KNN)';
require __DIR__ . '/templates/header.php';
?>
<div class="page-title">
  <h3>Lapisan Data-Driven - K-Nearest Neighbors</h3>
  <p class="lead-small">Lapisan pembelajaran mesin ini melengkapi model keputusan SAW-KMKK. KNN mempelajari pola dari fitur objektif vendor (hasil normalisasi OCDS) lalu memvalidasi apakah keputusan model konsisten secara data: vendor dengan fitur serupa seharusnya berada pada kelas kualitas yang serupa.</p>
</div>

<?php if (($eng['empty'] ?? true)): ?>
  <div class="alert alert-warning">Belum ada data. Jalankan <a href="sync.php">Sync</a> lebih dulu.</div>
<?php require __DIR__ . '/templates/footer.php'; return; endif; ?>

<?php
$rows = $eng['rows'];
$samples = [];
foreach ($rows as $r) {
    $samples[] = [
        'kode'  => $r['alt']['kode'],
        'nama'  => $r['alt']['nama'],
        'fitur' => [ (float)$r['ratio']['C1'], (float)$r['ratio']['C2'], (float)$r['ratio']['C3'] ],
        'kelas' => knnKelas((int)$r['P']),
        'saw'   => (float)$r['saw'],
        'p'     => (int)$r['P'],
    ];
}
$n = count($samples);
$k = max(1, min(3, $n - 1));
$loo = knnLoo($samples, $k);
$kelasList = ['Unggulan','Layak','Berisiko'];
$dist = [];
foreach ($samples as $s) { $dist[$s['kelas']] = ($dist[$s['kelas']] ?? 0) + 1; }
// scatter: x = skor SAW, y = rata-rata fitur benefit (C2,C3); ukuran = C1
$scatter = [];
foreach ($kelasList as $kc) { $scatter[$kc] = []; }
foreach ($samples as $s) {
    $scatter[$s['kelas']][] = ['x'=>round($s['saw'],4), 'y'=>round(($s['fitur'][1]+$s['fitur'][2])/2,4), 'kode'=>$s['kode'], 'nama'=>$s['nama']];
}
// Zoom sumbu ke rentang data (dengan padding) agar titik menyebar, tidak menumpuk di sudut
$xs = array_map(fn($s)=>$s['saw'], $samples);
$ys = array_map(fn($s)=>($s['fitur'][1]+$s['fitur'][2])/2, $samples);
$pad = 0.06;
$xmin = max(0, round(min($xs) - $pad, 2)); $xmax = min(1, round(max($xs) + $pad, 2));
$ymin = max(0, round(min($ys) - $pad, 2)); $ymax = min(1, round(max($ys) + $pad, 2));
if ($xmax - $xmin < 0.1) { $xmin = max(0,$xmin-0.05); $xmax = min(1,$xmax+0.05); }
if ($ymax - $ymin < 0.1) { $ymin = max(0,$ymin-0.05); $ymax = min(1,$ymax+0.05); }
?>

<div class="row">
  <div class="col-md-3 col-6 mb-3"><div class="stat"><div class="label">Parameter k</div><h2><?= $k ?></h2><div class="hint">tetangga terdekat (berbobot 1/jarak)</div></div></div>
  <div class="col-md-3 col-6 mb-3"><div class="stat"><div class="label">Sampel Vendor</div><h2><?= $n ?></h2><div class="hint">fitur 3 dimensi (C1,C2,C3)</div></div></div>
  <div class="col-md-3 col-6 mb-3"><div class="stat"><div class="label">Akurasi LOO-CV</div><h2><?= number_format($loo['accuracy']*100,1) ?>%</h2><div class="hint"><?= $loo['match'] ?>/<?= $loo['n'] ?> prediksi cocok dengan model</div></div></div>
  <div class="col-md-3 col-6 mb-3"><div class="stat"><div class="label">Kelas Muncul</div><h2><?= count(array_filter($dist)) ?> / 3</h2><div class="hint">dari 3 kemungkinan (Unggulan/Layak/Berisiko)</div></div></div>
</div>

<div class="row">
  <div class="col-lg-7 mb-3 d-flex"><div class="card w-100"><div class="card-header">Peta Sebaran KNN (ruang fitur)</div><div class="card-body chartbox">
    <div class="chart-grow"><canvas id="knnScatter"></canvas></div>
    <p class="small-muted mb-0 mt-2">Sumbu X = skor SAW; sumbu Y = rata-rata fitur benefit (reputasi &amp; keandalan). Warna = kelas kualitas. Titik yang berdekatan adalah &ldquo;tetangga&rdquo; &mdash; dasar keputusan KNN. Sumbu di-zoom ke rentang data (<?= $xmin ?>&ndash;<?= $xmax ?> / <?= $ymin ?>&ndash;<?= $ymax ?>) agar sebaran terlihat jelas.</p>
  </div></div></div>
  <div class="col-lg-5 mb-3 d-flex"><div class="card w-100"><div class="card-header">Komposisi Kelas (label model)</div><div class="card-body chartbox">
    <div class="chart-grow"><canvas id="knnDist"></canvas></div>
    <p class="small-muted mb-0 mt-2">Distribusi kelas hasil pelabelan dari predikat KMKK-OWA, dipakai sebagai target pembelajaran KNN.</p>
  </div></div></div>
</div>

<div class="card mb-3"><div class="card-header">Validasi Silang &amp; Tetangga Terdekat per Vendor</div><div class="card-body">
  <p class="explain">Setiap vendor diuji dengan <i>Leave-One-Out</i>: vendor dikeluarkan, lalu kelasnya diprediksi dari <?= $k ?> tetangga terdekat sisanya. Kecocokan tinggi menandakan keputusan SAW-KMKK selaras dengan pola data objektif (bukan sekadar bobot subjektif).</p>
  <div class="table-responsive"><table class="table table-sm tc mb-0">
    <thead><tr><th>Vendor</th><th>Fitur [C1, C2, C3]</th><th>Kelas Model</th><th>Prediksi KNN</th><th><?= $k ?> Tetangga Terdekat (jarak)</th><th>Cocok</th></tr></thead><tbody>
    <?php foreach ($loo['rows'] as $idx => $row): $s=$samples[$idx]; ?><tr>
      <td><b><?= htmlspecialchars($row['kode']) ?></b> &middot; <span class="small-muted"><?= htmlspecialchars($row['nama']) ?></span></td>
      <td class="mono small">[<?= number_format($s['fitur'][0],2) ?>, <?= number_format($s['fitur'][1],2) ?>, <?= number_format($s['fitur'][2],2) ?>]</td>
      <td><span class="badge-soft" style="border-color:<?= knnWarnaKelas($row['aktual']) ?>;color:<?= knnWarnaKelas($row['aktual']) ?>"><?= $row['aktual'] ?></span></td>
      <td><span class="badge-soft" style="border-color:<?= knnWarnaKelas($row['pred']) ?>;color:<?= knnWarnaKelas($row['pred']) ?>"><?= $row['pred'] ?></span></td>
      <td class="small"><?php foreach ($row['neighbors'] as $nb): ?><span class="mr-2 mono"><?= htmlspecialchars($nb['kode']) ?> (<?= number_format($nb['dist'],3) ?>)</span><?php endforeach; ?></td>
      <td><?= $row['ok'] ? '<span class="tag-ok">cocok</span>' : '<span class="tag-no">beda</span>' ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  <p class="small-muted mt-2 mb-0">Catatan: jarak memakai Euclidean pada fitur ternormalisasi; bobot suara tetangga = 1/jarak sehingga tetangga lebih dekat lebih berpengaruh.</p>
</div></div>

<div class="card mb-3"><div class="card-header">Cara Kerja &amp; Posisi dalam Sistem</div><div class="card-body">
  <div class="flow d-flex flex-wrap align-items-center mb-2">
    <span class="flow-step">Fitur OCDS [C1,C2,C3]</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">Label kelas (predikat KMKK)</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">KNN (k=<?= $k ?>, Euclidean)</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step flow-end">Validasi LOO-CV</span>
  </div>
  <ul class="small-muted mb-0">
    <li><b>Peran:</b> lapisan data-driven sebagai pemeriksa konsistensi &amp; mesin rekomendasi &ldquo;vendor serupa&rdquo;, bukan pengganti keputusan SAW-KMKK.</li>
    <li><b>Fitur:</b> rasio ternormalisasi (0&ndash;1) agar setiap kriteria setara skala &mdash; syarat penting agar jarak KNN adil.</li>
    <li><b>Kelas:</b> Unggulan (P&ge;6), Layak (P 4&ndash;5), Berisiko (P&le;3).</li>
    <li><b>Indikator kunci:</b> akurasi LOO-CV makin tinggi &rarr; keputusan model makin terdukung data.</li>
  </ul>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const SC = <?= json_encode($scatter) ?>;
const KW = {Unggulan:'#1f7a8c',Layak:'#2c6fb5',Berisiko:'#c1440e'};
Chart.defaults.color='#333'; Chart.defaults.borderColor='#e6e6e1'; Chart.defaults.font.family='inherit';
const dsets = Object.keys(SC).filter(k=>SC[k].length).map(k=>({label:k,data:SC[k],backgroundColor:KW[k],borderColor:'#fff',borderWidth:1,pointRadius:7,pointHoverRadius:9}));
new Chart(knnScatter,{type:'scatter',data:{datasets:dsets},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'},tooltip:{callbacks:{label:(c)=>c.raw.kode+' — '+c.raw.nama+'  (SAW='+c.raw.x+', benefit='+c.raw.y+')'}}},scales:{x:{title:{display:true,text:'Skor SAW'},min:<?= $xmin ?>,max:<?= $xmax ?>},y:{title:{display:true,text:'Rata-rata fitur benefit (C2,C3)'},min:<?= $ymin ?>,max:<?= $ymax ?>}}}});
const DL=<?= json_encode(array_keys(array_filter($dist))) ?>, DV=<?= json_encode(array_values(array_filter($dist))) ?>;
new Chart(knnDist,{type:'doughnut',data:{labels:DL,datasets:[{data:DV,backgroundColor:DL.map(l=>KW[l]||'#6c757d'),borderColor:'#fff',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},cutout:'58%'}});
</script>
<?php require __DIR__ . '/templates/footer.php'; ?>
