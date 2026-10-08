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

$sql = "SELECT id, ip, port, proto, country, country_zh, score, ping_ms, speed_bps, last_seen, uploader_count
        FROM nodes WHERE last_seen >= :since AND uploader_count >= :minUp";
$params = [':since' => $since, ':minUp' => $minUp];
if ($country !== '') { $sql .= " AND country = :country"; $params[':country'] = $country; }
$sql .= " ORDER BY uploader_count DESC, score DESC, last_seen DESC LIMIT " . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nodes = $stmt->fetchAll();
json_out(['ok' => true, 'nodes' => $nodes, 'count' => count($nodes)]);
