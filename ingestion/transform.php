<?php
/**
 * Lapisan TRANSFORM: payload OCDS mentah -> baris kontrak ternormalisasi (stg_kontrak).
 * Satu baris per (award x supplier).
 */
require_once __DIR__ . '/../config/koneksi.php';

function firstCpv(array $rel): ?string {
    foreach (($rel['tender']['items'] ?? []) as $it) {
        if (isset($it['classification']['id'])) return (string)$it['classification']['id'];
    }
    return $rel['tender']['classification']['id'] ?? null;
}

function transformOcds(PDO $db, array $cfg): int {
    $db->exec("TRUNCATE TABLE stg_kontrak");
    $ins = $db->prepare(
        "INSERT INTO stg_kontrak(ocid,supplier_name,amount,currency,tender_status,contract_status,award_date,category)
         VALUES(?,?,?,?,?,?,?,?)"
    );
    $rows = 0;
    $st = $db->query("SELECT payload FROM raw_ocds");
    while ($p = $st->fetch()) {
        $rel = json_decode($p['payload'], true);
        if (!is_array($rel)) continue;
        $ocid         = $rel['ocid'] ?? null;
        $tenderStatus = $rel['tender']['status'] ?? null;
        $cpv          = firstCpv($rel);
        $contractByAward = [];
        foreach (($rel['contracts'] ?? []) as $c) {
            if (isset($c['awardID'])) $contractByAward[$c['awardID']] = $c['status'] ?? null;
        }
        foreach (($rel['awards'] ?? []) as $aw) {
            $amount     = $aw['value']['amount'] ?? null;
            $currency   = $aw['value']['currency'] ?? $cfg['currency'];
            $date       = $aw['date'] ?? ($rel['date'] ?? null);
            $awardDate  = $date ? substr((string)$date, 0, 10) : null;
            // Pakai status kontrak bila ada; jika tidak, jatuh ke status award (data award-stage CF).
            $cStatus    = $contractByAward[$aw['id'] ?? ''] ?? ($aw['status'] ?? null);
            foreach (($aw['suppliers'] ?? []) as $sup) {
                $name = $sup['name'] ?? null;
                if (!$name) continue;
                $ins->execute([$ocid, $name, $amount, $currency, $tenderStatus, $cStatus, $awardDate, $cpv]);
                $rows++;
            }
        }
    }
    return $rows;
}
