<?php
// GET /api/nodes.php?country=JP&limit=100&min_uploaders=2  下载节点
declare(strict_types=1);
require __DIR__ . '/db.php';

$country = trim((string)($_GET['country'] ?? ''));
$limit = (int)($_GET['limit'] ?? 100);
$limit = max(1, min(500, $limit));
$minUp = (int)($_GET['min_uploaders'] ?? 1);
$maxAgeH = (int)($_GET['max_age_h'] ?? 48);  // 只要 48 小时内有人上报过的

$pdo = pool_db();
$since = time() - $maxAgeH * 3600;
$since24 = time() - 86400;

// 24h 存活投票统计：有投票的节点按可用率优先排
$sql = "SELECT id, ip, port, proto, country, country_zh, score, ping_ms, speed_bps, config_b64, last_seen, uploader_count, upload_count,
        (SELECT COUNT(*) FROM node_votes v WHERE v.node_id = nodes.id AND v.created_at >= $since24) AS votes_total,
        (SELECT SUM(v.ok) FROM node_votes v WHERE v.node_id = nodes.id AND v.created_at >= $since24) AS votes_ok
        FROM nodes WHERE last_seen >= :since AND uploader_count >= :minUp";
$params = [':since' => $since, ':minUp' => $minUp];
if ($country !== '') { $sql .= " AND country = :country"; $params[':country'] = $country; }
$sql .= " ORDER BY (votes_total > 0) DESC, (CAST(votes_ok AS REAL) / NULLIF(votes_total, 0)) DESC, uploader_count DESC, score DESC, last_seen DESC LIMIT " . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nodes = $stmt->fetchAll();
foreach ($nodes as &$n) {
    $t = (int)($n['votes_total'] ?? 0);
    $n['avail_24h'] = $t > 0 ? (int)round(100 * (int)$n['votes_ok'] / $t) : null;
    unset($n['votes_total'], $n['votes_ok']);
}
unset($n);
json_out(['ok' => true, 'nodes' => $nodes, 'count' => count($nodes)]);
