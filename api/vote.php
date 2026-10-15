<?php
// POST /api/vote.php  上报节点存活投票（连接成功/失败）
// Body: {"device_id":"abc123","votes":[{"id":"vpn1","ok":true},{"id":"vpn2","ok":false}]}
declare(strict_types=1);
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { json_out(['ok' => true]); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_out(['ok' => false, 'error' => 'POST only'], 405); }

$in = json_in();
$votes = $in['votes'] ?? [];
if (!is_array($votes) || count($votes) === 0) { json_out(['ok' => false, 'error' => 'votes 为空']); }
if (count($votes) > 200) { json_out(['ok' => false, 'error' => '单次最多 200 条']); }

$device_id = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)($in['device_id'] ?? '')));
$device_id = substr($device_id, 0, 64);
if ($device_id === '') {
    $ip = client_ip();
    $device_id = 'ip:' . ($ip !== '' ? $ip : 'unknown');
}

$pdo = pool_db();
$now = time();
$hour = (int)($now / 3600);

// 收集有效投票
$clean = [];
foreach ($votes as $v) {
    if (!is_array($v)) continue;
    $id = trim((string)($v['id'] ?? ''));
    if ($id === '' || strlen($id) > 128) continue;
    $clean[$id] = !empty($v['ok']) ? 1 : 0;
}
$skipped = count($votes) - count($clean);
if (!$clean) json_out(['ok' => false, 'error' => '无有效投票']);

// 只接受池中已存在的节点
$ph = implode(',', array_fill(0, count($clean), '?'));
$ex = $pdo->prepare("SELECT id FROM nodes WHERE id IN ($ph)");
$ex->execute(array_keys($clean));
$existing = array_column($ex->fetchAll(), 'id');

$stmt = $pdo->prepare("INSERT OR REPLACE INTO node_votes (node_id, device_id, hour, ok, created_at) VALUES (?, ?, ?, ?, ?)");
$counted = 0;
foreach ($existing as $id) {
    $stmt->execute([$id, $device_id, $hour, $clean[$id], $now]);
    $counted++;
}

json_out(['ok' => true, 'counted' => $counted, 'skipped' => $skipped + (count($clean) - $counted)]);
