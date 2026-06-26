<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../helpers/skala.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$CFG   = appConfig();
$PAGE  = $PAGE  ?? '';
$TITLE = $TITLE ?? $CFG['app_name'];

if (!function_exists('badgeSkala')) {
    function badgeSkala(int $i): string {
        return '<span class="badge badge-skala" style="background:' . skalaWarna($i) . '" title="' . skalaNama($i) . '">'
             . skalaKode($i) . ' (' . $i . ')</span>';
    }
}
if (!function_exists('fmtMoney')) {
    function fmtMoney($v, string $cur = 'GBP'): string {
        if ($v === null || (float)$v <= 0) return '-';
        return $cur . ' ' . number_format((float)$v, 0);
    }
}
if (!function_exists('selSkala')) {
    function selSkala(string $name, int $val): string {
        $h = '<select name="' . $name . '" class="form-control form-control-sm">';
        for ($i = 1; $i <= 7; $i++) {
            $h .= '<option value="' . $i . '"' . ($i === $val ? ' selected' : '') . '>'
                . $i . ' - ' . skalaKode($i) . ' (' . skalaNama($i) . ')</option>';
        }
        return $h . '</select>';
    }
}
$nav = [
    'index.php'       => 'Dashboard',
    'data_master.php' => 'Data Master',
    'sync.php'        => 'Sync',
    'penilaian.php'   => 'Penilaian',
    'perhitungan.php' => 'Perhitungan',
    'analytics.php'   => 'Analytics',
    'knn.php'         => 'Data-Driven',
    'about.php'       => 'Tentang',
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($TITLE) ?></title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-nav">
  <div class="container-fluid app-pad">
    <a class="navbar-brand" href="index.php">SPK SAW-KMKK</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ml-auto">
        <?php foreach ($nav as $href => $label): ?>
          <li class="nav-item <?= $PAGE === $href ? 'active' : '' ?>"><a class="nav-link" href="<?= $href ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container-fluid app-pad app-main">
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success"><?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
