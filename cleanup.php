<?php
// CLI: php cleanup.php  删除 7 天无人上报的节点（挂 cron 每天跑）
// 0 3 * * * /usr/bin/php /path/to/yu-pool-api/cleanup.php
declare(strict_types=1);
require __DIR__ . '/api/db.php';
$pdo = pool_db();
$cutoff = time() - 7 * 86400;
$n = $pdo->exec("DELETE FROM nodes WHERE last_seen < $cutoff");
echo date('Y-m-d H:i:s') . " cleaned $n stale nodes\n";
