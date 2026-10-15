<?php
// 节点上传记录页
declare(strict_types=1);
require __DIR__ . '/api/db.php';
$pdo = pool_db();
$id = trim((string)($_GET['id'] ?? ''));
if ($id === '') { http_response_code(400); exit('缺少 id'); }

$node = $pdo->prepare("SELECT * FROM nodes WHERE id = ?");
$node->execute([$id]);
$n = $node->fetch();
if (!$n) { http_response_code(404); exit('节点不存在'); }

$logs = $pdo->prepare("SELECT uploader_ip, device_id, created_at FROM upload_log WHERE node_id = ? ORDER BY created_at DESC LIMIT 100");
$logs->execute([$id]);
$rows = $logs->fetchAll();

function mask_device(string $d): string {
    if (str_starts_with($d, 'ip:')) return mask_ip(substr($d, 3));
    $d = trim($d);
    if ($d === '') return '-';
    return strlen($d) > 12 ? substr($d, 0, 8) . '…' : $d;
}

function ago2(int $ts): string {
    $d = time() - $ts;
    if ($d < 60) return $d . '秒前';
    if ($d < 3600) return (int)($d / 60) . '分钟前';
    if ($d < 86400) return (int)($d / 3600) . '小时前';
    return date('Y-m-d H:i', $ts);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>上传记录 - <?= htmlspecialchars($id) ?></title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;padding:20px;max-width:700px;margin:0 auto}
.card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:16px}
h2{font-size:18px;margin-bottom:8px}
.info{font-size:13px;color:#666;line-height:1.8}
.ip{font-family:monospace}
table{width:100%;border-collapse:collapse;font-size:13px;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
th{background:#f8fafc;text-align:left;padding:10px 14px;color:#666}
td{padding:9px 14px;border-top:1px solid #f0f2f5}
a{color:#2563eb;text-decoration:none}
.back{display:inline-block;margin-bottom:12px;font-size:14px}
.tbl-wrap{overflow-x:auto;background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.tbl-wrap table{box-shadow:none;border-radius:0;min-width:480px}
@media (max-width:768px){
  body{padding:12px}
  .card{padding:14px}
  th,td{padding:8px 10px;font-size:12px}
}
</style>
</head>
<body>
<a class="back" href="index.php">← 返回节点池</a>
<div class="card">
  <h2><?= htmlspecialchars($n['ip']) ?>:<?= $n['port'] ?> <span style="font-size:12px;color:#888"><?= strtoupper($n['proto']) ?></span></h2>
  <div class="info">
    节点 ID：<span class="ip"><?= htmlspecialchars($n['id']) ?></span><br>
    国家：<?= htmlspecialchars($n['country_zh'] ?: $n['country']) ?><br>
    累计被 <?= $n['uploader_count'] ?> 台独立设备确认 · 共 <?= $n['upload_count'] ?: count($rows) ?> 次上传
  </div>
</div>
<div class="tbl-wrap">
<table>
<tr><th>#</th><th>设备标识</th><th>上传者 IP（已脱敏）</th><th>时间</th></tr>
<?php foreach ($rows as $i => $r): ?>
<tr><td><?= $i + 1 ?></td><td class="ip"><?= htmlspecialchars(mask_device($r['device_id'] ?? '')) ?></td><td class="ip"><?= mask_ip($r['uploader_ip']) ?></td><td><?= ago2((int)$r['created_at']) ?></td></tr>
<?php endforeach; ?>
</table>
</div>
</body>
</html>
