<?php
// GET /api/stats.php  池子统计
declare(strict_types=1);
require __DIR__ . '/db.php';
$pdo = pool_db();
$total = (int)$pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
$day = (int)$pdo->query("SELECT COUNT(*) FROM nodes WHERE last_seen >= " . (time() - 86400))->fetchColumn();
$countries = $pdo->query("SELECT country, country_zh, COUNT(*) c FROM nodes GROUP BY country ORDER BY c DESC LIMIT 30")->fetchAll();
json_out(['ok' => true, 'total' => $total, 'active_24h' => $day, 'countries' => $countries]);
