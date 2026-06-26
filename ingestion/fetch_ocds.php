<?php
/**
 * Lapisan EXTRACT: menarik release OCDS dari sumber data kontrak publik UK.
 * Sumber utama: Contracts Finder (cakupan luas termasuk NHS, ambang lebih rendah -> volume besar).
 * Sumber sekunder: Find a Tender (notice bernilai tinggi).
 * Scan TERBARU -> mundur (publishedTo=now, publishedFrom=now-Ntahun), paginasi cursor (links.next).
 * Graceful fallback ke snapshot lokal bila API tidak dapat dihubungi (NFR-03).
 */
require_once __DIR__ . '/../config/koneksi.php';

function httpGetJson(string $url, int $timeout): ?array {
    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'SPK-SAW-KMKK/1.0 (academic OCDS ingest)',
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) $raw = null;
    }
    if ($raw === null) {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "Accept: application/json\r\n"]]);
        $r = @file_get_contents($url, false, $ctx);
        if ($r !== false) $raw = $r;
    }
    if ($raw === null) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

/** Kumpulkan seluruh kode CPV dari berbagai lokasi pada sebuah release. */
function collectCpvs(array $release): array {
    $cpvs = [];
    $push = function ($node) use (&$cpvs) {
        if (isset($node['classification']['id'])) $cpvs[] = (string)$node['classification']['id'];
        foreach (($node['additionalClassifications'] ?? []) as $ac) {
            if (isset($ac['id'])) $cpvs[] = (string)$ac['id'];
        }
    };
    $tender = $release['tender'] ?? [];
    $push($tender);
    foreach (($tender['items'] ?? []) as $it) $push($it);
    foreach (($release['awards'] ?? []) as $aw) {
        foreach (($aw['items'] ?? []) as $it) $push($it);
    }
    return $cpvs;
}

/** Teks gabungan untuk pencocokan kata kunci. */
function releaseHaystack(array $release): string {
    $t = $release['tender'] ?? [];
    $parts = [$t['title'] ?? '', $t['description'] ?? ''];
    foreach (($t['items'] ?? []) as $it) $parts[] = $it['description'] ?? '';
    foreach (($release['awards'] ?? []) as $aw) {
        $parts[] = $aw['title'] ?? '';
        $parts[] = $aw['description'] ?? '';
    }
    return strtolower(implode(' ', array_filter($parts)));
}

function ocdsMatchesHealth(array $release, array $prefixes, array $keywords = []): bool {
    $cpvs = collectCpvs($release);
    foreach ($cpvs as $c) {
        foreach ($prefixes as $p) {
            if (strncmp($c, (string)$p, strlen((string)$p)) === 0) return true;
        }
    }
    if ($keywords) {
        $hay = releaseHaystack($release);
        if ($hay !== '') {
            foreach ($keywords as $kw) {
                if ($kw !== '' && strpos($hay, strtolower($kw)) !== false) return true;
            }
        }
    }
    return false;
}

function insertRaw(PDO $db, array $rel, string $source): void {
    static $st = null;
    if ($st === null) {
        $st = $db->prepare("INSERT INTO raw_ocds(ocid,payload,fetched_at,source) VALUES(?,?,NOW(),?)");
    }
    $ocid = $rel['ocid'] ?? ($rel['id'] ?? null);
    $st->execute([$ocid, safeJsonEncode($rel), $source]);
}

/** Encode array menjadi JSON yang dijamin valid (substitusi UTF-8 invalid). */
function safeJsonEncode($value): string {
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    $json = json_encode($value, $flags);
    if ($json === false || $json === '' || $json === null) $json = json_encode(['_error' => 'encode_failed']);
    return ($json === false) ? '{}' : $json;
}

/**
 * Loop satu feed OCDS via paginasi cursor (links.next).
 * @return array ['ok'=>bool (server merespon), 'fetched'=>int]
 */
function fetchFeed(PDO $db, array $o, string $startUrl, string $sourceLabel, int &$fetched, int $startTs): bool {
    $url        = $startUrl;
    $pages      = 0;
    $okAny      = false;
    $maxSeconds = (int)($o['max_seconds'] ?? 90);
    $maxPages   = (int)($o['max_pages'] ?? 60);
    $target     = (int)($o['target_health'] ?? 0);
    $prefixes   = $o['cpv_prefixes'] ?? ['33'];
    $keywords   = $o['health_keywords'] ?? [];
    while ($url && $pages < $maxPages) {
        if (function_exists('set_time_limit')) @set_time_limit(0);
        $data = httpGetJson($url, (int)($o['timeout'] ?? 20));
        if ($data === null) break;
        $okAny = true;
        foreach (($data['releases'] ?? []) as $rel) {
            if (!ocdsMatchesHealth($rel, $prefixes, $keywords)) continue;
            insertRaw($db, $rel, $sourceLabel);
            $fetched++;
        }
        $pages++;
        if ($maxSeconds > 0 && (time() - $startTs) >= $maxSeconds) break;
        if ($target > 0 && $fetched >= $target) break;
        $next = $data['links']['next'] ?? null;
        $url  = (is_string($next) && $next !== $url) ? $next : null;
        if (!empty($o['page_pause_s'])) sleep((int)$o['page_pause_s']);
    }
    return $okAny;
}

function buildStartUrl(array $o, string $source): ?string {
    $from = (new DateTime('-' . (int)($o['years_back'] ?? 5) . ' years'))->format('Y-m-d\TH:i:s');
    $to   = (new DateTime('now'))->format('Y-m-d\TH:i:s');
    if ($source === 'contracts-finder') {
        if (empty($o['cf_endpoint'])) return null;
        return $o['cf_endpoint']
            . '?publishedFrom=' . rawurlencode($from)
            . '&publishedTo=' . rawurlencode($to)
            . '&stages=' . rawurlencode($o['cf_stages'] ?? 'award')
            . '&limit=' . (int)($o['cf_limit'] ?? 100);
    }
    if ($source === 'find-a-tender') {
        if (empty($o['endpoint'])) return null;
        // FTS hanya mendukung updatedFrom (ascending); tetap dibatasi anggaran waktu.
        return $o['endpoint'] . '?updatedFrom=' . rawurlencode($from);
    }
    return null;
}

function loadSnapshot(PDO $db, array $cfg): int {
    $file = __DIR__ . '/../data/snapshot_ocds.json';
    if (!is_file($file)) return 0;
    $j = json_decode((string)file_get_contents($file), true);
    $o = $cfg['ocds'];
    $n = 0;
    foreach (($j['releases'] ?? []) as $rel) {
        if (!ocdsMatchesHealth($rel, $o['cpv_prefixes'], $o['health_keywords'] ?? [])) continue;
        insertRaw($db, $rel, 'snapshot');
        $n++;
    }
    return $n;
}

/**
 * Simpan isi raw_ocds saat ini menjadi snapshot offline (data/snapshot_ocds.json).
 * @return array ['file'=>string,'count'=>int,'bytes'=>int]
 */
function saveSnapshot(PDO $db): array {
    $file = __DIR__ . '/../data/snapshot_ocds.json';
    $rows = $db->query("SELECT payload FROM raw_ocds ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $releases = [];
    foreach ($rows as $p) {
        $d = json_decode((string)$p, true);
        if (is_array($d)) $releases[] = $d;
    }
    $package = [
        'uri'           => 'snapshot://spk_sawkmkk_rt',
        'version'       => '1.1',
        'publishedDate' => date('c'),
        'publisher'     => ['name' => 'Live OCDS snapshot (saved from realtime sync)'],
        'license'       => 'http://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/',
        'count'         => count($releases),
        'releases'      => $releases,
    ];
    $bytes = @file_put_contents($file, safeJsonEncode($package));
    return ['file' => $file, 'count' => count($releases), 'bytes' => (int)$bytes];
}

/**
 * @return array ['source'=>string,'live'=>bool,'fetched'=>int]
 */
function fetchOcds(PDO $db, array $cfg, bool $forceFallback = false): array {
    if (function_exists('set_time_limit'))    @set_time_limit(0);
    if (function_exists('ignore_user_abort')) @ignore_user_abort(true);
    $db->exec("TRUNCATE TABLE raw_ocds");
    $o       = $cfg['ocds'];
    $fetched = 0;
    $live    = false;
    $used    = [];
    $startTs = time();
    $maxSeconds = (int)($o['max_seconds'] ?? 90);

    if (!$forceFallback) {
        $sources = $o['sources'] ?? ['contracts-finder', 'find-a-tender'];
        foreach ($sources as $src) {
            $startUrl = buildStartUrl($o, $src);
            if (!$startUrl) continue;
            $ok = fetchFeed($db, $o, $startUrl, $src, $fetched, $startTs);
            if ($ok) { $live = true; $used[] = $src; }
            // Cukup bila target tercapai atau waktu habis.
            if ((int)($o['target_health'] ?? 0) > 0 && $fetched >= (int)$o['target_health']) break;
            if ($maxSeconds > 0 && (time() - $startTs) >= $maxSeconds) break;
        }
    }

    if ($fetched === 0) {
        if ($forceFallback || !empty($o['use_fallback'])) {
            $fetched = loadSnapshot($db, $cfg);
            return ['source' => 'snapshot', 'live' => false, 'fetched' => $fetched];
        }
        return ['source' => ($used ? implode('+', $used) : 'live'), 'live' => $live, 'fetched' => 0];
    }
    return ['source' => ($used ? implode('+', $used) : 'live'), 'live' => $live, 'fetched' => $fetched];
}
