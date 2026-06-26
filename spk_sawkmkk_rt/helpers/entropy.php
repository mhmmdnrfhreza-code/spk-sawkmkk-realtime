<?php
/**
 * Pembobotan objektif via metode Entropy (opsional, FR-10).
 * Masukan: matriks rasio ternormalisasi [0..1] per kriteria.
 */
function entropyWeights(array $matrix, array $crit = ['C1','C2','C3']): array {
    $n = count($matrix);
    if ($n === 0) return array_fill_keys($crit, 1 / max(1, count($crit)));

    $sum = array_fill_keys($crit, 0.0);
    foreach ($matrix as $row) {
        foreach ($crit as $c) { $sum[$c] += (float)($row[$c] ?? 0); }
    }
    $k = 1 / log(max(2, $n));
    $E = [];
    foreach ($crit as $c) {
        $e = 0.0;
        foreach ($matrix as $row) {
            $p = $sum[$c] > 0 ? ((float)($row[$c] ?? 0) / $sum[$c]) : 0.0;
            if ($p > 0) { $e += $p * log($p); }
        }
        $E[$c] = -$k * $e;
    }
    $d = []; $dsum = 0.0;
    foreach ($crit as $c) { $d[$c] = 1 - $E[$c]; $dsum += $d[$c]; }
    $w = [];
    foreach ($crit as $c) { $w[$c] = $dsum > 0 ? $d[$c] / $dsum : 1 / count($crit); }
    return $w;
}
