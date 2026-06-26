<?php
/**
 * Installer satu kali. Jika berhasil, langsung masuk dashboard.
 * Tidak ada landing pilihan agar UX ringkas: /install.php -> /index.php.
 */
require_once __DIR__ . '/config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$cfg = appConfig();
try {
    $root = pdoConnect(false);
    $root->exec("CREATE DATABASE IF NOT EXISTS `" . $cfg['db']['name'] . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $db  = pdoConnect(true);
    $sql = (string) file_get_contents(__DIR__ . '/database/spk_sawkmkk_rt.sql');
    $chunks = explode(';', $sql);
    $applied = 0;
    foreach ($chunks as $chunk) {
        $lines = array_filter(array_map('rtrim', explode("\n", $chunk)), static function ($l) {
            $t = ltrim($l);
            return $t !== '' && substr($t, 0, 2) !== '--';
        });
        $stmt = trim(implode("\n", $lines));
        if ($stmt === '') continue;
        $db->exec($stmt);
        $applied++;
    }

    require_once __DIR__ . '/ingestion/sync_all.php';
    $res = runSync($db, $cfg, true); // snapshot agar instalasi cepat dan stabil
    if (!$res['ok']) throw new RuntimeException($res['error'] ?? 'Sinkronisasi awal gagal.');

    $_SESSION['flash_success'] = 'Instalasi selesai. Skema database diterapkan (' . $applied . ' statement) dan data awal snapshot berhasil dimuat. Untuk data aktual, buka menu Sync lalu tekan Sync Now.';
    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    $err = $e->getMessage();
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalasi gagal - <?= htmlspecialchars($cfg['app_name']) ?></title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css"></head><body>
<div class="container" style="max-width:760px;margin-top:48px">
  <div class="card"><div class="card-body">
    <h3 class="mb-3">Instalasi gagal</h3>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <p class="small-muted mb-0">Pastikan MySQL Laragon berjalan dan kredensial pada <span class="mono">config/config.php</span> benar.</p>
  </div></div>
</div></body></html>
