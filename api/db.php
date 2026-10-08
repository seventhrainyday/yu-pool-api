<?php
// 公共节点池 - 数据库公共库 (PHP 8.0+)
declare(strict_types=1);

function pool_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $pdo = new PDO('sqlite:' . $dir . '/pool.db', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS nodes (
        id TEXT PRIMARY KEY,
        ip TEXT NOT NULL,
        port INTEGER NOT NULL DEFAULT 1194,
        proto TEXT NOT NULL DEFAULT 'udp',
        country TEXT DEFAULT '',
        country_zh TEXT DEFAULT '',
        score INTEGER DEFAULT 0,
        ping_ms REAL DEFAULT 0,
        speed_bps INTEGER DEFAULT 0,
        last_seen INTEGER NOT NULL,
        uploader_count INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_country ON nodes(country)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_last_seen ON nodes(last_seen)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_score ON nodes(score DESC)");
    return $pdo;
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_in(): array {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}
