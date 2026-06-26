<?php
/**
 * Mesin SPK: SAW (normalisasi) + KMKK Minimax (individu) + OWA Maximin (kelompok).
 * Menggabungkan data-driven (mart OCDS) dengan model-driven (skala kualitatif).
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../helpers/skala.php';
require_once __DIR__ . '/../helpers/saw.php';
require_once __DIR__ . '/../helpers/kmkk.php';

function getKriteria(PDO $db): array {
    $out = [];
    foreach ($db->query("SELECT * FROM tb_kriteria ORDER BY kode")->fetchAll() as $r) {
        $out[$r['kode']] = $r;
    }
    return $out;
}
function getPakar(PDO $db): array {
    return $db->query("SELECT * FROM tb_pakar ORDER BY id")->fetchAll();
}
function getAlternatifMart(PDO $db): array {
    return $db->query(
        "SELECT a.id,a.kode,a.nama,m.median_harga,m.jumlah_award,m.rasio_selesai
         FROM tb_alternatif a JOIN mart_vendor_kriteria m ON m.supplier_name=a.nama
         ORDER BY m.jumlah_award DESC, a.id"
    )->fetchAll();
}
function getOverrides(PDO $db): array {
    $map = [];
    $sql = "SELECT p.id_alt, k.kode AS kode, p.id_pakar, p.nilai_index
            FROM tb_penilaian p JOIN tb_kriteria k ON k.id=p.id_krit";
    foreach ($db->query($sql)->fetchAll() as $r) {
        $map[(int)$r['id_alt']][$r['kode']][(int)$r['id_pakar']] = (int)$r['nilai_index'];
    }
    return $map;
}

function runEngine(PDO $db, array $cfg): array {
    $q        = (int)$cfg['q'];
    $kriteria = getKriteria($db);
    $pakar    = getPakar($db);
    $bobotIndex = [];
    foreach ($kriteria as $kode => $k) { $bobotIndex[$kode] = (int)$k['bobot_index']; }

    $alts = getAlternatifMart($db);
    $base = [
        'empty' => empty($alts), 'r' => count($pakar), 'q' => $q,
        'pakar' => $pakar, 'bobotIndex' => $bobotIndex, 'kriteria' => $kriteria, 'rows' => [],
    ];
    if (empty($alts)) return $base;

    // Faktor normalisasi SAW
    $hargaPos = array_filter(array_map(fn($a) => (float)$a['median_harga'], $alts), fn($v) => $v > 0);
    $minHarga = $hargaPos ? min($hargaPos) : 0.0;
    $maxAward = max(array_map(fn($a) => (float)$a['jumlah_award'], $alts)) ?: 1.0;
    $maxRasio = max(array_map(fn($a) => (float)$a['rasio_selesai'], $alts)) ?: 1.0;

    $sumB = array_sum($bobotIndex) ?: 1;
    $bobotRatio = [];
    foreach ($bobotIndex as $k => $v) { $bobotRatio[$k] = $v / $sumB; }

    $overrides = getOverrides($db);
    $rows = [];
    foreach ($alts as $a) {
        $ratio = [
            'C1' => sawNormCost((float)$a['median_harga'], $minHarga),
            'C2' => sawNormBenefit((float)$a['jumlah_award'], $maxAward),
            'C3' => sawNormBenefit((float)$a['rasio_selesai'], $maxRasio),
        ];
        $skalaBase = [
            'C1' => mapRatioToSkala($ratio['C1'], $q),
            'C2' => mapRatioToSkala($ratio['C2'], $q),
            'C3' => mapRatioToSkala($ratio['C3'], $q),
        ];
        $indiv = [];
        $pVals = [];
        foreach ($pakar as $pk) {
            $pid = (int)$pk['id'];
            $ratings = [
                'C1' => $skalaBase['C1'], // objektif dari data, sama untuk semua pakar
                'C2' => $overrides[$a['id']]['C2'][$pid] ?? $skalaBase['C2'],
                'C3' => $overrides[$a['id']]['C3'][$pid] ?? $skalaBase['C3'],
            ];
            $km = kmkkIndividu($ratings, $bobotIndex, $q);
            $indiv[$pid] = ['ratings' => $ratings, 'P' => $km['P'], 'terms' => $km['terms']];
            $pVals[] = $km['P'];
        }
        $owa = agregasiOWA($pVals, $q);
        $rows[] = [
            'alt' => $a, 'ratio' => $ratio, 'skalaBase' => $skalaBase,
            'indiv' => $indiv, 'owa' => $owa, 'P' => $owa['P'],
            'saw' => sawScore($ratio, $bobotRatio),
        ];
    }
    usort($rows, function ($x, $y) {
        if ($y['P'] !== $x['P']) return $y['P'] <=> $x['P'];
        return $y['saw'] <=> $x['saw'];
    });
    $i = 1; foreach ($rows as &$r) { $r['rank'] = $i++; } unset($r);
    $base['rows'] = $rows;
    return $base;
}

function persistHasil(PDO $db, array $eng): void {
    $db->exec("DELETE FROM tb_hasil");
    if (empty($eng['rows'])) return;
    $ins = $db->prepare(
        "INSERT INTO tb_hasil(id_alt,supplier_name,p_index,p_label,saw_score,ranking,computed_at)
         VALUES(?,?,?,?,?,?,NOW())"
    );
    foreach ($eng['rows'] as $r) {
        $ins->execute([
            (int)$r['alt']['id'], $r['alt']['nama'], (int)$r['P'], skalaKode((int)$r['P']),
            round($r['saw'], 4), (int)$r['rank'],
        ]);
    }
}
