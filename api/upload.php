<?php
// POST /api/upload.php  上传可用节点（去重）
// Body: {"nodes": [{"id":"vpn123","ip":"1.2.3.4","port":1194,"proto":"udp","country":"JP","country_zh":"日本","score":100,"ping_ms":50}, ...]}
declare(strict_types=1);
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { json_out(['ok' => true]); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_out(['ok' => false, 'error' => 'POST only'], 405); }

$in = json_in();
$nodes = $in['nodes'] ?? [];
if (!is_array($nodes) || count($nodes) === 0) { json_out(['ok' => false, 'error' => 'nodes 为空']); }
if (count($nodes) > 200) { json_out(['ok' => false, 'error' => '单次最多 200 个']); }

$pdo = pool_db();
$now = time();
$added = 0; $updated = 0; $skipped = 0;

$stmt = $pdo->prepare("INSERT INTO nodes
    (id, ip, port, proto, country, country_zh, score, ping_ms, speed_bps, last_seen, uploader_count, created_at)
    VALUES (:id, :ip, :port, :proto, :country, :country_zh, :score, :ping_ms, :speed_bps, :now, 1, :now)
    ON CONFLICT(id) DO UPDATE SET
        last_seen = :now,
        uploader_count = uploader_count + 1,
        score = MAX(score, excluded.score),
        ping_ms = CASE WHEN excluded.ping_ms > 0 THEN excluded.ping_ms ELSE ping_ms END");

foreach ($nodes as $n) {
    if (!is_array($n)) { $skipped++; continue; }
    $id = trim((string)($n['id'] ?? ''));
    $ip = trim((string)($n['ip'] ?? ''));
    // id 为空时用 ip:port:proto 生成
    if ($id === '' && $ip !== '') {
        $id = 'c_' . substr(hash('sha256', $ip . ':' . ($n['port'] ?? 1194) . ':' . ($n['proto'] ?? 'udp')), 0, 16);
    }
    if ($id === '' || !filter_var($ip, FILTER_VALIDATE_IP)) { $skipped++; continue; }
    $port = (int)($n['port'] ?? 1194);
    if ($port < 1 || $port > 65535) { $skipped++; continue; }
    $proto = strtolower(trim((string)($n['proto'] ?? 'udp')));
    if (!in_array($proto, ['udp', 'tcp'], true)) $proto = 'udp';

    // 判断是新增还是更新（用于统计）
    $exists = $pdo->prepare("SELECT 1 FROM nodes WHERE id = ?");
    $exists->execute([$id]);
    $isNew = !$exists->fetch();

    $stmt->execute([
        ':id' => $id, ':ip' => $ip, ':port' => $port, ':proto' => $proto,
        ':country' => trim((string)($n['country'] ?? '')),
        ':country_zh' => trim((string)($n['country_zh'] ?? '')),
        ':score' => (int)($n['score'] ?? 0),
        ':ping_ms' => (float)($n['ping_ms'] ?? 0),
        ':speed_bps' => (int)($n['speed_bps'] ?? 0),
        ':now' => $now,
    ]);
    $isNew ? $added++ : $updated++;
}

json_out(['ok' => true, 'added' => $added, 'updated' => $updated, 'skipped' => $skipped]);
