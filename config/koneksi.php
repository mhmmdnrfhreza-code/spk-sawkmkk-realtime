<?php
/**
 * Koneksi database via PDO (prepared statements - anti SQL injection, NFR-02).
 */

function appConfig(): array {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/config.php'; }
    return $c;
}

/**
 * Buat koneksi PDO. $withDb=false dipakai installer untuk CREATE DATABASE.
 */
function pdoConnect(bool $withDb = true): PDO {
    $c = appConfig()['db'];
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port']
         . ($withDb ? (';dbname=' . $c['name']) : '')
         . ';charset=utf8mb4';
    try {
        return new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die('<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f0c0c0;background:#fff6f6;border-radius:10px">'
          . '<h2 style="color:#b02a37">&#9888; Koneksi database gagal</h2>'
          . '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
          . '<p>Pastikan layanan <b>MySQL</b> pada Laragon sudah berjalan, lalu jalankan '
          . '<a href="install.php">install.php</a> satu kali untuk menyiapkan database.</p></div>');
    }
}

/** Helper query aman dengan parameter. */
function q(PDO $db, string $sql, array $params = []): PDOStatement {
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st;
}
