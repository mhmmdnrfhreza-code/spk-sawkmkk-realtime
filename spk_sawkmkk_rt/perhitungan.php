<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/lib/engine.php';
$cfg = appConfig();
$db  = pdoConnect();
$eng = runEngine($db, $cfg);
$cur = $cfg['currency'];
$q   = (int)$cfg['q'];
$r0  = (int)$eng['r'];
$PAGE = 'perhitungan.php'; $TITLE = 'Perhitungan';
require __DIR__ . '/templates/header.php';
?>
<div class="page-title">
  <h3>Perhitungan Hibrid SAW-KMKK</h3>
  <p class="lead-small">Transparansi langkah demi langkah &mdash; seleksi vendor alat kesehatan. Parameter: r = <?= $r0 ?> pakar, q = <?= $q ?> tingkat skala (S<sub>1</sub> terburuk &hellip; S<sub><?= $q ?></sub> terbaik). Setiap angka pada halaman ini dihitung langsung dari data, bukan nilai statis.</p>
</div>

<?php if (($eng['empty'] ?? true)): ?>
  <div class="alert alert-warning">Belum ada data vendor. Buka <a href="sync.php">Sync</a> lalu tekan <b>Sync Now</b>.</div>
<?php require __DIR__ . '/templates/footer.php'; return; endif; ?>

<?php
$rows = $eng['rows'];
$hargaPos = array_filter(array_map(fn($r)=>(float)$r['alt']['median_harga'],$rows), fn($v)=>$v>0);
$minHarga = $hargaPos ? min($hargaPos) : 0.0;
$maxAward = max(array_map(fn($r)=>(float)$r['alt']['jumlah_award'],$rows)) ?: 1.0;
$maxRasio = max(array_map(fn($r)=>(float)$r['alt']['rasio_selesai'],$rows)) ?: 1.0;
$bi = $eng['bobotIndex'];
?>

<div class="card mb-3"><div class="card-body">
  <div class="small-muted mb-2"><b>Alur perhitungan (baca kiri ke kanan):</b></div>
  <div class="flow d-flex flex-wrap align-items-center">
    <span class="flow-step">1. Data OCDS</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">2. Normalisasi SAW</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">3. Matriks rating</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">4. KMKK Minimax</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">5. Kuantor Q</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step">6. OWA Maximin</span><span class="flow-arrow">&rarr;</span>
    <span class="flow-step flow-end">7. Peringkat</span>
  </div>
</div></div>

<!-- LANGKAH 1 -->
<div class="card step-card"><div class="card-header"><span class="step-badge">1</span>Normalisasi SAW dan pemetaan ke skala S<sub>1</sub>&ndash;S<sub><?= $q ?></sub></div>
  <div class="card-body">
    <div class="formula-box mb-2">
      <b>Cost (C1):</b> r<sub>i</sub> = min(x) / x<sub>i</sub> &nbsp;&bull;&nbsp; <b>Benefit (C2, C3):</b> r<sub>i</sub> = x<sub>i</sub> / max(x) &nbsp;&bull;&nbsp; <b>Skala:</b> S<sub>i</sub> = round[ r<sub>i</sub> &times; (q &minus; 1) ] + 1
    </div>
    <p class="explain">SAW menyamakan satuan tiap kriteria ke rentang 0&ndash;1. C1 (harga) bersifat <i>cost</i> sehingga makin murah makin baik; C2 (jumlah award) dan C3 (rasio selesai) bersifat <i>benefit</i> sehingga makin besar makin baik. Nilai rasio lalu dipetakan ke skala kualitatif agar dapat diproses KMKK.</p>
    <div class="small-muted mb-2">Konstanta normalisasi: min(harga) = <span class="mono"><?= fmtMoney($minHarga,$cur) ?></span>, max(award) = <span class="mono"><?= (int)$maxAward ?></span>, max(rasio) = <span class="mono"><?= number_format($maxRasio,2) ?></span>.</div>
    <div class="table-responsive"><table class="table table-sm tc mb-0">
      <thead><tr><th rowspan="2">Vendor</th><th colspan="3" class="text-center">C1 &mdash; Harga (cost)</th><th colspan="3" class="text-center">C2 &mdash; Jumlah Award (benefit)</th><th colspan="3" class="text-center">C3 &mdash; Rasio Selesai (benefit)</th></tr>
      <tr><th>x<sub>i</sub></th><th>r<sub>i</sub></th><th>S</th><th>x<sub>i</sub></th><th>r<sub>i</sub></th><th>S</th><th>x<sub>i</sub></th><th>r<sub>i</sub></th><th>S</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?><tr>
        <td><b><?= htmlspecialchars($r['alt']['kode']) ?></b> &middot; <span class="small-muted"><?= htmlspecialchars($r['alt']['nama']) ?></span></td>
        <td class="mono"><?= fmtMoney($r['alt']['median_harga'],$cur) ?></td><td class="mono"><?= number_format($r['ratio']['C1'],3) ?></td><td><?= badgeSkala($r['skalaBase']['C1']) ?></td>
        <td class="mono"><?= (int)$r['alt']['jumlah_award'] ?></td><td class="mono"><?= number_format($r['ratio']['C2'],3) ?></td><td><?= badgeSkala($r['skalaBase']['C2']) ?></td>
        <td class="mono"><?= number_format((float)$r['alt']['rasio_selesai'],2) ?></td><td class="mono"><?= number_format($r['ratio']['C3'],3) ?></td><td><?= badgeSkala($r['skalaBase']['C3']) ?></td>
      </tr><?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="small-muted mt-2 mb-0">Contoh baca (vendor <?= htmlspecialchars($rows[0]['alt']['kode']) ?>, C1): r = <?= fmtMoney($minHarga,$cur) ?> / <?= fmtMoney($rows[0]['alt']['median_harga'],$cur) ?> = <?= number_format($rows[0]['ratio']['C1'],3) ?> &rarr; S = round(<?= number_format($rows[0]['ratio']['C1'],3) ?> &times; <?= $q-1 ?>) + 1 = <?= badgeSkala($rows[0]['skalaBase']['C1']) ?>.</p>
  </div>
</div>

<!-- LANGKAH 2 -->
<div class="card step-card"><div class="card-header"><span class="step-badge">2</span>Matriks rating per pakar (R<sub>ij</sub><sup>k</sup>)</div>
  <div class="card-body">
    <p class="explain">C1 berasal dari data objektif sehingga sama untuk semua pakar. C2 dan C3 mengikuti hasil SAW kecuali bila pakar memberi penilaian khusus pada menu Penilaian. Berikut rating tiap pakar terhadap setiap vendor.</p>
    <?php foreach ($eng['pakar'] as $pk): $pid=(int)$pk['id']; ?>
      <div class="mb-2 mt-3"><b><?= htmlspecialchars($pk['kode']) ?></b> &mdash; <span class="small-muted"><?= htmlspecialchars($pk['nama']) ?><?= $pk['jabatan']?(' &middot; '.htmlspecialchars($pk['jabatan'])):'' ?></span></div>
      <div class="table-responsive"><table class="table table-sm tc mb-0">
        <thead><tr><th>Vendor</th><th>C1 Harga</th><th>C2 Reputasi</th><th>C3 Keandalan</th></tr></thead><tbody>
        <?php foreach ($rows as $r): $rt=$r['indiv'][$pid]['ratings']; ?><tr>
          <td><b><?= htmlspecialchars($r['alt']['kode']) ?></b></td>
          <td><?= badgeSkala($rt['C1']) ?></td><td><?= badgeSkala($rt['C2']) ?></td><td><?= badgeSkala($rt['C3']) ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endforeach; ?>
  </div>
</div>

<!-- LANGKAH 3 -->
<div class="card step-card"><div class="card-header"><span class="step-badge">3</span>KMKK Minimax untuk setiap pakar (P<sub>i</sub><sup>k</sup>)</div>
  <div class="card-body">
    <div class="formula-box mb-2">
      P<sub>i</sub><sup>k</sup> = Min<sub>j</sub> [ Neg(W<sub>j</sub>) &or; R<sub>ij</sub> ] , &nbsp; Neg(S<sub>i</sub>) = S<sub>(q&minus;i+1)</sub> , &nbsp; &or; = Max (OR)
    </div>
    <p class="explain">Bobot kriteria dinegasikan lalu di-OR-kan dengan rating; kriteria penting (bobot tinggi) menghasilkan Neg(W) rendah sehingga rating buruk tidak mudah tertutupi. Nilai pakar adalah elemen terlemah (Min) dari seluruh kriteria.</p>
    <p class="small-muted mb-3">Bobot &amp; negasinya:
      <?php foreach ($bi as $kk=>$bw): ?><span class="mr-3"><b><?= htmlspecialchars($kk) ?></b>: W = <?= badgeSkala((int)$bw) ?> &rarr; Neg(W) = <?= badgeSkala(negasi((int)$bw,$q)) ?></span><?php endforeach; ?>
    </p>
    <?php foreach ($eng['pakar'] as $pk): $pid=(int)$pk['id']; ?>
      <div class="mb-2 mt-3"><b><?= htmlspecialchars($pk['kode']) ?></b> &mdash; <span class="small-muted"><?= htmlspecialchars($pk['nama']) ?></span></div>
      <div class="table-responsive"><table class="table table-sm tc mb-0">
        <thead><tr><th>Vendor</th><th>C1: Neg(W)&or;R</th><th>C2: Neg(W)&or;R</th><th>C3: Neg(W)&or;R</th><th>P<sub>i</sub><sup>k</sup> = Min</th></tr></thead><tbody>
        <?php foreach ($rows as $r): $iv=$r['indiv'][$pid]; $byK=[]; foreach($iv['terms'] as $t){$byK[$t['krit']]=$t;} ?><tr>
          <td><b><?= htmlspecialchars($r['alt']['kode']) ?></b></td>
          <?php foreach (['C1','C2','C3'] as $kc): $t=$byK[$kc]; ?>
            <td class="small">max(<?= badgeSkala($t['neg']) ?>, <?= badgeSkala($t['rating']) ?>) = <?= badgeSkala($t['or']) ?></td>
          <?php endforeach; ?>
          <td><?= badgeSkala($iv['P']) ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endforeach; ?>
  </div>
</div>

<!-- LANGKAH 4 -->
<div class="card step-card"><div class="card-header"><span class="step-badge">4</span>Rekap nilai individu semua pakar</div>
  <div class="card-body">
    <p class="explain">Matriks ini merangkum P<sub>i</sub><sup>k</sup> dari Langkah 3 sebagai masukan agregasi kelompok OWA.</p>
    <div class="table-responsive"><table class="table table-sm tc mb-0">
      <thead><tr><th>Vendor</th><?php foreach ($eng['pakar'] as $pk): ?><th><?= htmlspecialchars($pk['kode']) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($rows as $r): ?><tr>
        <td><b><?= htmlspecialchars($r['alt']['kode']) ?></b> &middot; <span class="small-muted"><?= htmlspecialchars($r['alt']['nama']) ?></span></td>
        <?php foreach ($eng['pakar'] as $pk): $pid=(int)$pk['id']; ?><td><?= badgeSkala($r['indiv'][$pid]['P']) ?></td><?php endforeach; ?>
      </tr><?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>

<!-- LANGKAH 5 -->
<div class="card step-card"><div class="card-header"><span class="step-badge">5</span>Fungsi kuantor Q untuk agregasi OWA</div>
  <div class="card-body">
    <div class="formula-box mb-2">Q(k) = S<sub>b(k)</sub> , &nbsp; b(k) = round[ 1 + k(q&minus;1) / r ] , &nbsp; r = <?= $r0 ?>, q = <?= $q ?></div>
    <p class="explain">Q adalah kuantor linguistik &ldquo;sebagian besar&rdquo;. Semakin banyak pakar yang mendukung nilai tinggi, semakin besar bobot kuantor sehingga keputusan kelompok menguat.</p>
    <div class="table-responsive"><table class="table table-sm tc mb-0" style="max-width:560px">
      <thead><tr><th>k</th><th>b(k) = round[1 + k(q&minus;1)/r]</th><th>b(k)</th><th>Q(k) = S<sub>b(k)</sub></th></tr></thead><tbody>
      <?php for ($k=1;$k<=$r0;$k++): $bk=(int)round(1+($k*($q-1)/$r0)); $bkc=max(1,min($q,$bk)); ?><tr>
        <td><?= $k ?></td>
        <td class="mono">round[1 + <?= $k ?>&times;<?= $q-1 ?>/<?= $r0 ?>] = round(<?= number_format(1+($k*($q-1)/$r0),3) ?>)</td>
        <td class="mono"><?= $bkc ?></td>
        <td><?= badgeSkala(fungsiQ($k,$r0,$q)) ?></td>
      </tr><?php endfor; ?>
      </tbody></table></div>
  </div>
</div>

<!-- LANGKAH 6 -->
<div class="card step-card"><div class="card-header"><span class="step-badge">6</span>OWA Maximin untuk keputusan kelompok (P<sub>i</sub>)</div>
  <div class="card-body">
    <div class="formula-box mb-2">P<sub>i</sub> = Max<sub>j</sub> [ Q(j) &and; B<sub>(j)</sub> ] , &nbsp; B<sub>(1)</sub> &ge; B<sub>(2)</sub> &ge; &hellip; &ge; B<sub>(r)</sub> (P individu diurutkan menurun) , &nbsp; &and; = Min (AND)</div>
    <p class="explain">Nilai individu diurutkan dari tertinggi, lalu dipasangkan dengan kuantor Q. OWA mengambil nilai kelompok terbaik (Max) yang masih didukung secara memadai oleh Q.</p>
    <div class="table-responsive"><table class="table table-sm tc mb-0">
      <thead><tr><th>Vendor</th><th>B<sub>(j)</sub> terurut</th><th>Term: Q(j) &and; B<sub>(j)</sub></th><th>P<sub>i</sub> = Max</th></tr></thead><tbody>
      <?php foreach ($rows as $r): $o=$r['owa']; ?><tr>
        <td><b><?= htmlspecialchars($r['alt']['kode']) ?></b></td>
        <td><?php foreach ($o['sorted'] as $b): ?><?= badgeSkala($b) ?> <?php endforeach; ?></td>
        <td class="small"><?php foreach ($o['terms'] as $t): ?><span class="mr-2">min(<?= badgeSkala($t['Q']) ?>, <?= badgeSkala($t['B']) ?>) = <?= badgeSkala($t['and']) ?></span><?php endforeach; ?></td>
        <td><?= badgeSkala($o['P']) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>

<!-- LANGKAH 7 -->
<div class="card step-card"><div class="card-header"><span class="step-badge">7</span>Peringkat akhir vendor</div>
  <div class="card-body">
    <p class="explain">Vendor diurutkan menurun berdasarkan predikat kelompok P<sub>i</sub> (KMKK-OWA). Bila predikat sama, skor SAW terbobot menjadi tie-break agar urutan deterministik.</p>
    <div class="table-responsive"><table class="table table-sm tc mb-0">
      <thead><tr><th>#</th><th>Vendor</th><th>Predikat P<sub>i</sub></th><th>Skor SAW</th><th>Interpretasi</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?><tr class="<?= $r['rank']===1?'winner':'' ?>">
        <td class="rank-one"><?= $r['rank'] ?></td>
        <td><b><?= htmlspecialchars($r['alt']['kode']) ?></b> &middot; <?= htmlspecialchars($r['alt']['nama']) ?></td>
        <td><?= badgeSkala($r['P']) ?></td>
        <td class="mono"><?= number_format($r['saw'],4) ?></td>
        <td><?= htmlspecialchars(skalaNama($r['P'])) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
