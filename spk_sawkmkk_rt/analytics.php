<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/lib/engine.php';
$cfg = appConfig();
$db  = pdoConnect();
$eng = runEngine($db, $cfg);
$cur = $cfg['currency'];

$labels=[]; $saw=[]; $pidx=[]; $c1=[]; $c2=[]; $c3=[]; $names=[];
foreach (($eng['rows'] ?? []) as $r) {
    $labels[] = $r['alt']['kode'];
    $names[]  = $r['alt']['nama'];
    $saw[]    = round($r['saw'], 4);
    $pidx[]   = (int)$r['P'];
    $c1[]     = round($r['ratio']['C1'], 4);
    $c2[]     = round($r['ratio']['C2'], 4);
    $c3[]     = round($r['ratio']['C3'], 4);
}
// Distribusi predikat + warna sesuai skala (indikator penting boleh berwarna)
$dist = [];
foreach ($pidx as $p) { $k = skalaKode($p); $dist[$k] = ($dist[$k] ?? 0) + 1; }
$kodeToIdx = array_flip($GLOBALS['SKALA_KODE']);
$distColors = array_map(fn($kode)=> skalaWarna((int)($kodeToIdx[$kode] ?? 4)), array_keys($dist));

$trend = $db->query("SELECT YEAR(award_date) y, COUNT(*) n, ROUND(AVG(amount)) avg_amount FROM stg_kontrak WHERE award_date IS NOT NULL GROUP BY YEAR(award_date) ORDER BY y")->fetchAll();
$trendY = array_map(fn($t)=>$t['y'], $trend);
$trendN = array_map(fn($t)=>(int)$t['n'], $trend);
$trendA = array_map(fn($t)=>(float)$t['avg_amount'], $trend);
$bobot = $eng['bobotIndex'] ?? ['C1'=>7,'C2'=>6,'C3'=>5];
$PAGE = 'analytics.php'; $TITLE = 'Analytics';
require __DIR__ . '/templates/header.php';
?>
<div class="page-title">
  <h3>Analytics Inti</h3>
  <p class="lead-small">Empat analisis inti yang langsung mendukung keputusan: posisi vendor, komposisi predikat, dinamika data kontrak, dan ketahanan peringkat terhadap perubahan bobot. Diagram di luar kebutuhan analisis dihilangkan agar halaman ringkas dan proporsional.</p>
</div>
<?php if (($eng['empty'] ?? true)): ?>
  <div class="alert alert-warning">Belum ada data. Jalankan Sync.</div>
<?php require __DIR__ . '/templates/footer.php'; return; endif; ?>

<div class="row">
  <div class="col-lg-8 mb-3 d-flex"><div class="card w-100"><div class="card-header">Peta Posisi Vendor &mdash; Predikat KMKK vs Skor SAW</div><div class="card-body chartbox"><div class="chart-grow"><canvas id="rankChart"></canvas></div><p class="small-muted mb-0 mt-2">Batang biru = skor SAW (0&ndash;1); garis oranye = indeks predikat (1&ndash;7). Vendor dengan predikat dan skor tertinggi berada di posisi terkuat.</p></div></div></div>
  <div class="col-lg-4 mb-3 d-flex"><div class="card w-100"><div class="card-header">Distribusi Predikat</div><div class="card-body chartbox"><div class="chart-grow"><canvas id="distChart"></canvas></div><p class="small-muted mb-0 mt-2">Sebaran kualitas kandidat pada shortlist SPK. Warna mengikuti tingkat skala S1&ndash;S7.</p></div></div></div>
</div>

<div class="row">
  <div class="col-lg-6 mb-3 d-flex"><div class="card w-100"><div class="card-header">Volume Kontrak per Tahun</div><div class="card-body chartbox"><div class="chart-grow"><canvas id="volumeChart"></canvas></div><p class="small-muted mb-0 mt-2">Memvalidasi bahwa data bersifat time-series dari jendela <?= (int)$cfg['ocds']['years_back'] ?> tahun terakhir.</p></div></div></div>
  <div class="col-lg-6 mb-3 d-flex"><div class="card w-100"><div class="card-header">Rata-rata Nilai Kontrak per Tahun</div><div class="card-body chartbox"><div class="chart-grow"><canvas id="valueChart"></canvas></div><p class="small-muted mb-0 mt-2">Kecenderungan nilai kontrak (<?= htmlspecialchars($cur) ?>) pada data yang tersinkron.</p></div></div></div>
</div>

<div class="card mb-3"><div class="card-header">Analisis Sensitivitas Bobot SAW</div><div class="card-body">
  <p class="small-muted">Simulasi <i>what-if</i> ini tidak mengubah database. Geser bobot untuk menguji apakah urutan vendor berubah. Predikat KMKK tetap memakai bobot resmi pada konfigurasi.</p>
  <div class="row">
    <div class="col-md-4"><div class="slider-label"><span>C1 Harga</span><b id="vC1"></b></div><input type="range" min="1" max="7" value="<?= (int)$bobot['C1'] ?>" id="wC1"></div>
    <div class="col-md-4"><div class="slider-label"><span>C2 Reputasi</span><b id="vC2"></b></div><input type="range" min="1" max="7" value="<?= (int)$bobot['C2'] ?>" id="wC2"></div>
    <div class="col-md-4"><div class="slider-label"><span>C3 Keandalan</span><b id="vC3"></b></div><input type="range" min="1" max="7" value="<?= (int)$bobot['C3'] ?>" id="wC3"></div>
  </div>
  <div class="chart-wrap-lg mt-3"><canvas id="sensChart"></canvas></div>
  <p class="small-muted mb-0 mt-2">Bila urutan teratas relatif stabil saat bobot digeser, keputusan dianggap robust.</p>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const L=<?= json_encode($labels) ?>, NAMES=<?= json_encode($names) ?>, SAW=<?= json_encode($saw) ?>, P=<?= json_encode($pidx) ?>,
      R1=<?= json_encode($c1) ?>, R2=<?= json_encode($c2) ?>, R3=<?= json_encode($c3) ?>;
const TY=<?= json_encode($trendY) ?>, TN=<?= json_encode($trendN) ?>, TA=<?= json_encode($trendA) ?>;
const DL=<?= json_encode(array_keys($dist)) ?>, DV=<?= json_encode(array_values($dist)) ?>, DC=<?= json_encode($distColors) ?>;
Chart.defaults.color = '#333'; Chart.defaults.borderColor = '#e6e6e1'; Chart.defaults.font.family = 'inherit';
new Chart(rankChart,{type:'bar',data:{labels:L,datasets:[
  {label:'Skor SAW',data:SAW,backgroundColor:'#2c6fb5',borderRadius:4,yAxisID:'y'},
  {label:'Indeks predikat',data:P,type:'line',borderColor:'#cc8800',backgroundColor:'#cc8800',pointRadius:3,yAxisID:'y1',tension:.25}
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'},tooltip:{callbacks:{afterTitle:(ctx)=>NAMES[ctx[0].dataIndex]}}},scales:{y:{beginAtZero:true,max:1,title:{display:true,text:'Skor SAW'}},y1:{beginAtZero:true,max:7,position:'right',grid:{drawOnChartArea:false},title:{display:true,text:'Indeks predikat'}}}}});
new Chart(distChart,{type:'doughnut',data:{labels:DL,datasets:[{data:DV,backgroundColor:DC,borderColor:'#fff',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},cutout:'58%'}});
new Chart(volumeChart,{type:'bar',data:{labels:TY,datasets:[{label:'Jumlah kontrak',data:TN,backgroundColor:'#1f7a8c',borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
new Chart(valueChart,{type:'line',data:{labels:TY,datasets:[{label:'Rata-rata <?= $cur ?>',data:TA,borderColor:'#0b5ed7',backgroundColor:'rgba(11,94,215,.12)',fill:true,tension:.25,pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
const sens=new Chart(sensChart,{type:'bar',data:{labels:L,datasets:[{label:'Skor SAW simulasi',data:SAW,backgroundColor:'#2c6fb5',borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,max:1}}}});
function recompute(){
  const w1=+wC1.value,w2=+wC2.value,w3=+wC3.value,s=(w1+w2+w3)||1;
  vC1.textContent=w1; vC2.textContent=w2; vC3.textContent=w3;
  const scores=L.map((_,i)=>+(R1[i]*w1/s+R2[i]*w2/s+R3[i]*w3/s).toFixed(4));
  const idx=scores.map((v,i)=>i).sort((a,b)=>scores[b]-scores[a]);
  sens.data.labels=idx.map(i=>L[i]); sens.data.datasets[0].data=idx.map(i=>scores[i]); sens.update();
}
[wC1,wC2,wC3].forEach(e=>e.addEventListener('input',recompute)); recompute();
</script>
<?php require __DIR__ . '/templates/footer.php'; ?>
