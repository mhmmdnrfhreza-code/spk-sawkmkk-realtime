<?php
/**
 * Orkestrasi pipeline realtime: fetch -> transform -> aggregate -> engine -> persist.
 * Mencatat setiap eksekusi ke tb_sync_log (audit & data freshness).
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../lib/engine.php';
require_once __DIR__ . '/fetch_ocds.php';
require_once __DIR__ . '/transform.php';
require_once __DIR__ . '/aggregate.php';

function runSync(PDO $db, array $cfg, bool $forceFallback = false): array {
    $db->prepare("INSERT INTO tb_sync_log(started_at,status,records_fetched,note) VALUES(NOW(),'running',0,'')")->execute();
    $logId = (int)$db->lastInsertId();
    try {
        $fetch   = fetchOcds($db, $cfg, $forceFallback);
        $kontrak = transformOcds($db, $cfg);
        $vendor  = aggregateOcds($db, $cfg);
        $eng     = runEngine($db, $cfg);
        persistHasil($db, $eng);

        $status = $vendor > 0 ? 'success' : 'empty';
        $note   = 'source=' . $fetch['source'] . '; live=' . ($fetch['live'] ? '1' : '0')
                . '; releases=' . $fetch['fetched'] . '; kontrak=' . $kontrak . '; vendor=' . $vendor;
        $db->prepare("UPDATE tb_sync_log SET finished_at=NOW(),records_fetched=?,status=?,note=? WHERE id=?")
           ->execute([$fetch['fetched'], $status, $note, $logId]);
        return ['ok' => true, 'fetch' => $fetch, 'kontrak' => $kontrak, 'vendor' => $vendor, 'note' => $note];
    } catch (Throwable $e) {
        $db->prepare("UPDATE tb_sync_log SET finished_at=NOW(),status='failed',note=? WHERE id=?")
           ->execute([substr($e->getMessage(), 0, 500), $logId]);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
