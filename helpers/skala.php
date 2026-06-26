<?php
/**
 * Definisi skala kualitatif S1..S7 dan operator dasar KMKK (Yager 1993).
 * S1=BS (Buruk Sekali) ... S7=S (Sempurna).
 */

$GLOBALS['SKALA_KODE'] = [1=>'BS',2=>'SK',3=>'K',4=>'Sd',5=>'B',6=>'SB',7=>'S'];
$GLOBALS['SKALA_NAMA'] = [
    1=>'Buruk Sekali', 2=>'Sangat Kurang', 3=>'Kurang', 4=>'Sedang',
    5=>'Baik', 6=>'Sangat Baik', 7=>'Sempurna',
];

function skalaKode(int $i): string { return $GLOBALS['SKALA_KODE'][$i] ?? ('S' . $i); }
function skalaNama(int $i): string { return $GLOBALS['SKALA_NAMA'][$i] ?? ('S' . $i); }
function clampSkala(int $i, int $q = 7): int { return max(1, min($q, $i)); }

// Negasi: Neg(S_i) = S_(q-i+1)
function negasi(int $i, int $q = 7): int { return clampSkala($q - $i + 1, $q); }

// Operator OR (gabungan / maks) dan AND (irisan / min) pada skala.
function opMax(int $a, int $b): int { return max($a, $b); }
function opMin(int $a, int $b): int { return min($a, $b); }

// Warna badge per skala untuk UI (Bootstrap-friendly hex).
function skalaWarna(int $i): string {
    $map = [1=>'#b02a37',2=>'#c1440e',3=>'#cc8800',4=>'#6c757d',5=>'#2f8f4e',6=>'#1f7a8c',7=>'#0b5ed7'];
    return $map[$i] ?? '#6c757d';
}
