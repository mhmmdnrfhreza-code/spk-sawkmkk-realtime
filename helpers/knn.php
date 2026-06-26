<?php
// Lapisan data-driven: klasifikasi KNN atas fitur vendor hasil OCDS.
// Fitur = rasio ternormalisasi SAW [C1,C2,C3] (0..1, makin besar makin baik).
// Label = kelas kualitas turunan predikat KMKK-OWA (validasi silang model).

function knnKelas(int $p): string {
    if ($p >= 6) return 'Unggulan';
    if ($p >= 4) return 'Layak';
    return 'Berisiko';
}
function knnWarnaKelas(string $k): string {
    return ['Unggulan'=>'#1f7a8c','Layak'=>'#2c6fb5','Berisiko'=>'#c1440e'][$k] ?? '#6c757d';
}
function knnJarak(array $a, array $b): float {
    $s = 0.0;
    foreach ($a as $i => $v) { $d = (float)$v - (float)($b[$i] ?? 0); $s += $d * $d; }
    return sqrt($s);
}
// $samples: array of ['kode'=>str,'nama'=>str,'fitur'=>[..],'kelas'=>str]
function knnPredict(array $samples, array $fitur, int $k, ?int $excludeIdx = null): array {
    $d = [];
    foreach ($samples as $i => $s) {
        if ($i === $excludeIdx) continue;
        $d[] = ['kode'=>$s['kode'], 'kelas'=>$s['kelas'], 'dist'=>knnJarak($fitur, $s['fitur'])];
    }
    usort($d, fn($x, $y) => $x['dist'] <=> $y['dist']);
    $nn = array_slice($d, 0, max(1, $k));
    $vote = [];
    foreach ($nn as $n) { $w = 1.0 / (1e-9 + $n['dist']); $vote[$n['kelas']] = ($vote[$n['kelas']] ?? 0) + $w; }
    arsort($vote);
    return ['pred'=>array_key_first($vote), 'neighbors'=>$nn, 'vote'=>$vote];
}
function knnLoo(array $samples, int $k): array {
    $match = 0; $rowsOut = []; $byClass = [];
    foreach ($samples as $i => $s) {
        $r  = knnPredict($samples, $s['fitur'], $k, $i);
        $ok = ($r['pred'] === $s['kelas']);
        if ($ok) $match++;
        $byClass[$s['kelas']]['n'] = ($byClass[$s['kelas']]['n'] ?? 0) + 1;
        if ($ok) $byClass[$s['kelas']]['ok'] = ($byClass[$s['kelas']]['ok'] ?? 0) + 1;
        $rowsOut[] = ['kode'=>$s['kode'], 'nama'=>$s['nama'], 'aktual'=>$s['kelas'], 'pred'=>$r['pred'], 'ok'=>$ok, 'neighbors'=>$r['neighbors']];
    }
    $n = count($samples);
    return ['accuracy'=>$n ? $match / $n : 0.0, 'match'=>$match, 'n'=>$n, 'rows'=>$rowsOut, 'byClass'=>$byClass];
}
