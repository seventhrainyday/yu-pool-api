<?php
// Yu-pool 在线更新：从 GitHub 拉取最新代码覆盖（绝不碰 data/）
// 首次访问自动生成 token（页面上明文显示一次，请保存），之后操作需带 token
declare(strict_types=1);
require __DIR__ . '/api/db.php';

const UPDATE_REPO_ZIP = 'https://codeload.github.com/seventhrainyday/yu-pool-api/zip/refs/heads/main';
// 允许覆盖的文件白名单（data/ 永不进入）
const UPDATE_FILES = ['index.php', 'history.php', 'update.php', 'cleanup.php', 'README.md',
    'api/db.php', 'api/upload.php', 'api/nodes.php', 'api/stats.php', 'api/vote.php'];

$tokenFile = __DIR__ . '/data/update_token';
$token = is_file($tokenFile) ? trim((string)file_get_contents($tokenFile)) : '';
$firstSetup = false;
if ($token === '') {
    $token = bin2hex(random_bytes(16));
    @mkdir(__DIR__ . '/data', 0755, true);
    file_put_contents($tokenFile, $token);
    @chmod($tokenFile, 0600);
    $firstSetup = true;
}

function update_json_out(array $d, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function do_update(): array {
    if (!extension_loaded('zip')) {
        return ['ok' => false, 'error' => 'PHP 缺 zip 扩展，请在 1Panel 的 PHP 管理里安装/启用 php-zip'];
    }
    $tmpZip = tempnam(sys_get_temp_dir(), 'pool_up_') . '.zip';
    $tmpDir = sys_get_temp_dir() . '/pool_up_' . bin2hex(random_bytes(6));
    try {
        // 下载：curl 扩展优先（SSL 更可靠），回退 file_get_contents
        $data = false;
        if (function_exists('curl_init')) {
            $ch = curl_init(UPDATE_REPO_ZIP);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'yu-pool-updater',
            ]);
            $data = curl_exec($ch);
            if (curl_errno($ch)) $data = false;
            curl_close($ch);
        }
        if ($data === false) {
            $ctx = stream_context_create(['http' => ['timeout' => 60, 'header' => "User-Agent: yu-pool-updater\r\n"]]);
            $data = @file_get_contents(UPDATE_REPO_ZIP, false, $ctx);
        }
        if ($data === false || strlen($data) < 1000 || substr($data, 0, 2) !== 'PK') {
            return ['ok' => false, 'error' => '下载更新包失败（GitHub 连接异常）'];
        }
        file_put_contents($tmpZip, $data);
        // 解压
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) return ['ok' => false, 'error' => '更新包解压失败'];
        @mkdir($tmpDir, 0755, true);
        $zip->extractTo($tmpDir);
        $zip->close();
        // 找到解压根目录（yu-pool-api-main/）
        $roots = array_values(array_filter(scandir($tmpDir), fn($e) => $e !== '.' && $e !== '..' && is_dir("$tmpDir/$e")));
        if (!$roots) return ['ok' => false, 'error' => '更新包结构异常'];
        $root = "$tmpDir/{$roots[0]}";
        foreach (UPDATE_FILES as $f) {
            if (!is_file("$root/$f")) return ['ok' => false, 'error' => "更新包缺少文件: $f"];
        }
        // 备份当前文件
        $bak = __DIR__ . '/data/backup/' . date('Ymd_His');
        @mkdir($bak . '/api', 0755, true);
        $backed = 0;
        foreach (UPDATE_FILES as $f) {
            if (is_file(__DIR__ . "/$f")) {
                @mkdir(dirname("$bak/$f"), 0755, true);
                if (@copy(__DIR__ . "/$f", "$bak/$f")) $backed++;
            }
        }
        // 覆盖
        $copied = 0;
        foreach (UPDATE_FILES as $f) {
            $dst = __DIR__ . "/$f";
            @mkdir(dirname($dst), 0755, true);
            if (@copy("$root/$f", $dst)) $copied++;
        }
        return ['ok' => true, 'copied' => $copied, 'backup' => $bak, 'backed_up' => $backed];
    } finally {
        @unlink($tmpZip);
        if (is_dir($tmpDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
            @rmdir($tmpDir);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
    $t = (string)($in['token'] ?? $_POST['token'] ?? '');
    if (!hash_equals($token, $t)) update_json_out(['ok' => false, 'error' => 'token 错误'], 403);
    update_json_out(do_update());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Yu-pool 在线更新</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f7fa;padding:24px;max-width:560px;margin:0 auto}
.card{background:#fff;border-radius:10px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
h2{font-size:18px;margin-bottom:12px}
input{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;margin:8px 0;font-family:monospace}
button{background:#2563eb;color:#fff;border:0;border-radius:8px;padding:10px 24px;font-size:14px;cursor:pointer}
button:disabled{background:#93c5fd}
.note{font-size:13px;color:#666;line-height:1.8;margin-top:12px}
.warn{background:#fef3c7;border-radius:8px;padding:10px 14px;font-size:13px;color:#92400e;margin-bottom:12px}
#result{margin-top:12px;font-size:13px;white-space:pre-wrap}
a{color:#2563eb}
</style>
</head>
<body>
<div class="card">
  <h2>🔄 Yu-pool 在线更新</h2>
  <?php if ($firstSetup): ?>
  <div class="warn">首次使用：已自动生成管理 token（仅显示这一次，请复制保存）：<br>
    <code style="font-size:15px;user-select:all"><?= htmlspecialchars($token) ?></code></div>
  <?php endif; ?>
  <div>管理 token：</div>
  <input id="token" type="text" placeholder="粘贴 token" value="<?= $firstSetup ? htmlspecialchars($token) : '' ?>">
  <button id="btn" onclick="doUpdate()">从 GitHub 拉取更新</button>
  <div id="result"></div>
  <div class="note">
    · 更新源：GitHub seventhrainyday/yu-pool-api 的 main 分支<br>
    · 只覆盖白名单内的 php/md 文件，<b>data/（数据库）绝不触碰</b><br>
    · 更新前自动备份旧文件到 data/backup/日期/<br>
    · 需要 PHP zip 扩展（1Panel → PHP 管理 → 安装扩展）
  </div>
  <div style="margin-top:12px"><a href="index.php">← 返回节点池</a></div>
</div>
<script>
async function doUpdate() {
  const btn = document.getElementById('btn'), box = document.getElementById('result');
  const token = document.getElementById('token').value.trim();
  if (!token) { box.textContent = '请先填写 token'; return; }
  btn.disabled = true; btn.textContent = '更新中…';
  box.textContent = '正在从 GitHub 下载…';
  try {
    const r = await fetch('update.php', {method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({token})});
    const j = await r.json();
    box.textContent = j.ok
      ? '✅ 更新成功：覆盖 ' + j.copied + ' 个文件，备份在 ' + j.backup
      : '❌ ' + (j.error || '更新失败');
  } catch(e) { box.textContent = '❌ 请求失败：' + e.message; }
  btn.disabled = false; btn.textContent = '从 GitHub 拉取更新';
}
</script>
</body>
</html>
