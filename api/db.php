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
        config_b64 TEXT DEFAULT '',
        last_seen INTEGER NOT NULL,
        uploader_count INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_country ON nodes(country)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_last_seen ON nodes(last_seen)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_score ON nodes(score DESC)");
    try { $pdo->exec("ALTER TABLE nodes ADD COLUMN config_b64 TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE nodes ADD COLUMN uploader_ip TEXT DEFAULT ''"); } catch (Exception $e) {}
    // v2: 总上传次数（每次上报都+1）；uploader_count 改为独立设备数
    try { $pdo->exec("ALTER TABLE nodes ADD COLUMN upload_count INTEGER DEFAULT 0"); } catch (Exception $e) {}
    // v2: 回填历史 upload_count
    try { $pdo->exec("UPDATE nodes SET upload_count = (SELECT COUNT(*) FROM upload_log WHERE upload_log.node_id = nodes.id) WHERE upload_count = 0"); } catch (Exception $e) {}
    // v2: 节点-设备去重表（确认数=独立设备数）
    $pdo->exec("CREATE TABLE IF NOT EXISTS node_devices (
        node_id TEXT NOT NULL,
        device_id TEXT NOT NULL,
        first_seen INTEGER NOT NULL,
        PRIMARY KEY (node_id, device_id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_nd_device ON node_devices(device_id)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS upload_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        node_id TEXT NOT NULL,
        uploader_ip TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_log_node ON upload_log(node_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_log_time ON upload_log(created_at DESC)");
    // v2: 上传日志带设备标识（老库 ALTER，新库建表时已无此列则补）
    try { $pdo->exec("ALTER TABLE upload_log ADD COLUMN device_id TEXT DEFAULT ''"); } catch (Exception $e) {}
    // v3: 存活投票（每设备每节点每小时一条，可覆盖）
    $pdo->exec("CREATE TABLE IF NOT EXISTS node_votes (
        node_id TEXT NOT NULL,
        device_id TEXT NOT NULL,
        hour INTEGER NOT NULL,
        ok INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        PRIMARY KEY (node_id, device_id, hour)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_votes_node ON node_votes(node_id, created_at DESC)");
    return $pdo;
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mask_ip(string $ip): string {
    // 隐藏后两段：1.2.3.4 -> 1.2.*.*
    $p = explode('.', $ip);
    if (count($p) === 4) return $p[0] . '.' . $p[1] . '.*.*';
    // IPv6 隐藏后 4 组
    if (strpos($ip, ':') !== false) {
        $g = explode(':', $ip);
        $n = count($g);
        for ($i = max(0, $n - 4); $i < $n; $i++) $g[$i] = '*';
        return implode(':', $g);
    }
    return '***';
}

function client_ip(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '';
}

function json_in(): array {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '{}', true);
    return is_array($d) ? $d : [];
}
