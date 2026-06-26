<?php
/**
 * Lapisan LOAD/aggregate: stg_kontrak -> mart_vendor_kriteria (per vendor),
 * lalu pilih top-N vendor sebagai alternatif (tb_alternatif).
 * C1 = median harga (cost), C2 = jumlah award (benefit), C3 = rasio selesai (benefit).
 */
require_once __DIR__ . '/../config/koneksi.php';

function medianOf(array $vals): float {
    $v = array_values(array_filter($vals, fn($x) => $x !== null));
    sort($v, SORT_NUMERIC);
    $n = count($v);
    if ($n === 0) return 0.0;
    $mid = intdiv($n, 2);
    return ($n % 2) ? (float)$v[$mid] : (float)(($v[$mid - 1] + $v[$mid]) / 2);
}

function aggregateOcds(PDO $db, array $cfg): int {
    $rows = $db->query("SELECT supplier_name,amount,contract_status,tender_status FROM stg_kontrak")->fetchAll();
    $agg = [];
    foreach ($rows as $r) {
        $s = $r['supplier_name'];
        if (!isset($agg[$s])) $agg[$s] = ['amounts' => [], 'total' => 0, 'done' => 0];
        if ($r['amount'] !== null) $agg[$s]['amounts'][] = (float)$r['amount'];
        $agg[$s]['total']++;
        $cs = strtolower((string)$r['contract_status']);
        $ts = strtolower((string)$r['tender_status']);
        if (in_array($cs, ['active', 'complete', 'closed'], true) || in_array($ts, ['complete', 'active'], true)) {
            $agg[$s]['done']++;
        }
    }

    $db->exec("TRUNCATE TABLE mart_vendor_kriteria");
    $insM = $db->prepare(
        "INSERT INTO mart_vendor_kriteria(supplier_name,median_harga,jumlah_award,rasio_selesai,last_sync)
         VALUES(?,?,?,?,NOW())"
    );
    $marts = [];
    foreach ($agg as $s => $d) {
        $median = medianOf($d['amounts']);
        $rasio  = $d['total'] > 0 ? round($d['done'] / $d['total'], 2) : 0.0;
        $insM->execute([$s, $median, $d['total'], $rasio]);
        $marts[] = ['nama' => $s, 'jumlah_award' => $d['total'], 'median' => $median];
    }

    // Bangun ulang alternatif sumber OCDS (CASCADE menghapus penilaian lama).
    $db->exec("DELETE FROM tb_alternatif WHERE sumber='ocds'");
    // Sertakan vendor dengan minimal 1 award (harga boleh kosong -> skor harga terendah),
    // agar vendor tidak hilang hanya karena nilai kontrak tidak dipublikasikan.
    $marts = array_values(array_filter($marts, fn($m) => $m['jumlah_award'] >= 1));
    usort($marts, fn($a, $b) => $b['jumlah_award'] <=> $a['jumlah_award']);
    $topN = (int)$cfg['top_n'];
    if ($topN > 0) $marts = array_slice($marts, 0, $topN);

    $insA = $db->prepare("INSERT INTO tb_alternatif(kode,nama,sumber) VALUES(?,?,'ocds')");
    $i = 1;
    foreach ($marts as $m) { $insA->execute(['V' . $i, $m['nama']]); $i++; }
    return count($marts);
}
