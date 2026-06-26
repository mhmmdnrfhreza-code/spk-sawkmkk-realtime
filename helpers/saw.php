<?php
/**
 * SAW (Simple Additive Weighting).
 * Menormalkan nilai numerik menjadi rasio [0..1] lalu memetakannya ke skala kualitatif S1..S7.
 */
require_once __DIR__ . '/skala.php';

// Kriteria COST (semakin kecil semakin baik):  r = min / nilai
function sawNormCost(float $nilai, float $minNilai): float {
    if ($nilai <= 0) return 0.0;
    return $minNilai / $nilai;
}

// Kriteria BENEFIT (semakin besar semakin baik): r = nilai / max
function sawNormBenefit(float $nilai, float $maxNilai): float {
    if ($maxNilai <= 0) return 0.0;
    return $nilai / $maxNilai;
}

// Petakan rasio [0..1] ke indeks skala 1..q  ->  index = round(r*(q-1)) + 1
function mapRatioToSkala(float $r, int $q = 7): int {
    $idx = (int) round($r * ($q - 1)) + 1;
    return max(1, min($q, $idx));
}

// Skor SAW numerik berbobot (untuk tie-break). $bobotRatio: kriteria => bobot ternormalisasi.
function sawScore(array $ratio, array $bobotRatio): float {
    $s = 0.0;
    foreach ($ratio as $k => $r) { $s += $r * ($bobotRatio[$k] ?? 0); }
    return $s;
}
