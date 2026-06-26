<?php
/**
 * Runner CLI untuk penjadwalan (cron Linux / Task Scheduler Windows).
 * Contoh cron harian 02:00:  0 2 * * * php /path/spk_sawkmkk_rt/ingestion/cron_sync.php >> /var/log/spk_sync.log 2>&1
 * Paksa snapshot (offline/demo):  php ingestion/cron_sync.php --fallback
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/sync_all.php';

$cfg   = appConfig();
$db    = pdoConnect();
$force = in_array('--fallback', $argv ?? [], true);
$res   = runSync($db, $cfg, $force);
echo date('c') . ' ' . ($res['ok'] ? ('OK ' . $res['note']) : ('FAIL ' . $res['error'])) . PHP_EOL;
