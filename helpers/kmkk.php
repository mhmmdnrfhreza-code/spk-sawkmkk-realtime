<?php
/**
 * KMKK (Keputusan Multi Kriteria Kualitatif) - Yager 1993.
 * Agregasi individu (Minimax) + agregasi kelompok (OWA Maximin).
 */
require_once __DIR__ . '/skala.php';

/**
 * Agregasi penilaian satu pakar (Minimax):
 *   P_ir = Min_j [ Neg(bobot_j) OR rating_ij ]
 * @param array $ratings    kode kriteria => indeks skala penilaian (1..q)
 * @param array $bobotIndex kode kriteria => indeks skala bobot (1..q)
 * @return array ['P'=>int, 'terms'=>[['krit','neg','rating','or']...]]
 */
function kmkkIndividu(array $ratings, array $bobotIndex, int $q = 7): array {
    $terms = [];
    $vals  = [];
    foreach ($ratings as $kode => $r) {
        $neg = negasi((int)($bobotIndex[$kode] ?? $q), $q);
        $or  = opMax($neg, (int)$r);
        $vals[] = $or;
        $terms[] = ['krit' => $kode, 'neg' => $neg, 'rating' => (int)$r, 'or' => $or];
    }
    return ['P' => (empty($vals) ? 1 : min($vals)), 'terms' => $terms];
}

// Fungsi kuantor (linguistic quantifier "most"):  b(k) = int[ 1 + k*(q-1)/r ]
function fungsiQ(int $k, int $r, int $q = 7): int {
    if ($r <= 0) return 1;
    $b = (int) round(1 + ($k * ($q - 1) / $r));
    return clampSkala($b, $q);
}

/**
 * Agregasi kelompok (OWA Maximin):
 *   urutkan nilai pakar menurun B_1 >= ... >= B_r, lalu
 *   P_i = Max_j [ Q(j) AND B_j ]
 * @param array $nilaiPakar daftar indeks skala hasil individu tiap pakar
 * @return array ['P'=>int,'sorted'=>[...],'terms'=>[['j','Q','B','and']...]]
 */
function agregasiOWA(array $nilaiPakar, int $q = 7): array {
    $b = array_map('intval', array_values($nilaiPakar));
    rsort($b);
    $r = count($b);
    $terms = [];
    $P = 0;
    for ($j = 1; $j <= $r; $j++) {
        $qq  = fungsiQ($j, $r, $q);
        $and = opMin($qq, $b[$j - 1]);
        $terms[] = ['j' => $j, 'Q' => $qq, 'B' => $b[$j - 1], 'and' => $and];
        $P = opMax($P, $and);
    }
    return ['P' => $P, 'sorted' => $b, 'terms' => $terms];
}
