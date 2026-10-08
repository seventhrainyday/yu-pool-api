<?php
// Yu-pool 公共节点池 - 首页（节点浏览）
declare(strict_types=1);
require __DIR__ . '/api/db.php';
$pdo = pool_db();

$country = trim((string)($_GET['country'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;
$offset = ($page - 1) * $per;

// 统计
$total = (int)$pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
$day = (int)$pdo->query("SELECT COUNT(*) FROM nodes WHERE last_seen >= " . (time() - 86400))->fetchColumn();
$uploads = (int)$pdo->query("SELECT COUNT(*) FROM upload_log")->fetchColumn();
$countries = $pdo->query("SELECT country, country_zh, COUNT(*) c FROM nodes GROUP BY country ORDER BY c DESC")->fetchAll();

// 节点列表
$where = "WHERE last_seen >= " . (time() - 48 * 3600);
$params = [];
if ($country !== '') { $where .= " AND country = ?"; $params[] = $country; }
$cnt = $pdo->prepare("SELECT COUNT(*) FROM nodes $where");
$cnt->execute($params);
$filtered = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($filtered / $per));

$stmt = $pdo->prepare("SELECT id, ip, port, proto, country, country_zh, score, ping_ms, uploader_count, uploader_ip, last_seen, created_at
    FROM nodes $where ORDER BY uploader_count DESC, score DESC, last_seen DESC LIMIT $per OFFSET $offset");
$stmt->execute($params);
$nodes = $stmt->fetchAll();

function ago(int $ts): string {
    $d = time() - $ts;
    if ($d < 60) return $d . '秒前';
    if ($d < 3600) return (int)($d / 60) . '分钟前';
    if ($d < 86400) return (int)($d / 3600) . '小时前';
    return (int)($d / 86400) . '天前';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Yu-pool 公共节点池</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;color:#333;padding:20px;max-width:1200px;margin:0 auto}
h1{font-size:24px;margin-bottom:4px}
.sub{color:#888;font-size:13px;margin-bottom:16px}
.stats{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.stat{background:#fff;border-radius:10px;padding:14px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);flex:1;min-width:140px}
.stat .n{font-size:26px;font-weight:700;color:#2563eb}
.stat .l{font-size:12px;color:#888;margin-top:2px}
.filter{background:#fff;border-radius:10px;padding:12px 16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.filter a{display:inline-block;padding:4px 12px;margin:2px;border-radius:16px;font-size:13px;color:#555;text-decoration:none;background:#f0f2f5}
.filter a.on{background:#2563eb;color:#fff}
table{width:100%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);border-collapse:collapse;font-size:13px}
th{background:#f8fafc;text-align:left;padding:10px 12px;color:#666;font-weight:600;white-space:nowrap}
td{padding:10px 12px;border-top:1px solid #f0f2f5;vertical-align:middle}
tr:hover td{background:#f8fafc}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;background:#e0f2fe;color:#0369a1}
.badge.hot{background:#fef3c7;color:#92400e}
.ip{font-family:monospace}
.pager{text-align:center;margin:16px 0}
.pager a{display:inline-block;padding:6px 14px;margin:0 4px;background:#fff;border-radius:8px;text-decoration:none;color:#2563eb;box-shadow:0 1px 3px rgba(0,0,0,.08);font-size:13px}
.hist{font-size:12px;color:#2563eb;cursor:pointer}
footer{text-align:center;color:#aaa;font-size:12px;margin-top:24px}
</style>
</head>
<body>
<h1>🌐 Yu-pool 公共节点池</h1>
<div class="sub">由 Yu-proxy 用户众包维护的可用节点 · 上传者 IP 已脱敏</div>

<div class="stats">
  <div class="stat"><div class="n"><?= $total ?></div><div class="l">池中节点</div></div>
  <div class="stat"><div class="n"><?= $day ?></div><div class="l">24h 活跃</div></div>
  <div class="stat"><div class="n"><?= $uploads ?></div><div class="l">累计上传（含重复）</div></div>
  <div class="stat"><div class="n"><?= count($countries) ?></div><div class="l">覆盖国家/地区</div></div>
</div>

<div class="filter">
  <a href="?" class="<?= $country === '' ? 'on' : '' ?>">全部</a>
  <?php foreach ($countries as $c): ?>
    <a href="?country=<?= urlencode($c['country']) ?>" class="<?= $country === $c['country'] ? 'on' : '' ?>"><?= htmlspecialchars($c['country_zh'] ?: $c['country']) ?> (<?= $c['c'] ?>)</a>
  <?php endforeach; ?>
</div>

<table>
<tr><th>节点</th><th>国家</th><th>分数</th><th>延迟</th><th>确认数</th><th>最后上传者</th><th>更新</th><th></th></tr>
<?php foreach ($nodes as $n): ?>
<tr>
  <td><span class="ip"><?= htmlspecialchars($n['ip']) ?>:<?= $n['port'] ?></span> <span class="badge"><?= strtoupper($n['proto']) ?></span></td>
  <td><?= htmlspecialchars($n['country_zh'] ?: $n['country']) ?></td>
  <td><?= $n['score'] ?></td>
  <td><?= $n['ping_ms'] ? round($n['ping_ms']) . 'ms' : '-' ?></td>
  <td><span class="badge <?= $n['uploader_count'] >= 3 ? 'hot' : '' ?>"><?= $n['uploader_count'] ?> 人</span></td>
  <td class="ip"><?= mask_ip($n['uploader_ip']) ?></td>
  <td><?= ago((int)$n['last_seen']) ?></td>
  <td><a class="hist" href="history.php?id=<?= urlencode($n['id']) ?>">上传记录</a></td>
</tr>
<?php endforeach; ?>
</table>

<div class="pager">
  <?php if ($page > 1): ?><a href="?country=<?= urlencode($country) ?>&page=<?= $page - 1 ?>">← 上一页</a><?php endif; ?>
  <span style="font-size:13px;color:#888">第 <?= $page ?> / <?= $pages ?> 页（共 <?= $filtered ?> 个）</span>
  <?php if ($page < $pages): ?><a href="?country=<?= urlencode($country) ?>&page=<?= $page + 1 ?>">下一页 →</a><?php endif; ?>
</div>

<footer>Yu-pool · 数据每 48 小时未更新则不再展示 · 7 天无人上报自动清理</footer>
</body>
</html>
