# Yu-pool 公共节点池 API

极简 PHP + SQLite，无框架依赖，PHP 7.4+ / 8.x 均可。

## 部署

1. 上传整个目录到 VPS，如 `/var/www/pool`
2. 确保 `data/` 可写：`mkdir -p data && chmod 755 data`
3. 需要 PHP SQLite3 扩展：`php -m | grep -i sqlite`
4. Nginx/Apache 把域名指向本目录，`api/` 下的 php 文件即接口
5. 加 cron 每天清理：`0 3 * * * php /var/www/pool/cleanup.php >> /var/log/pool-clean.log 2>&1`

## 接口

- `POST api/upload.php` — 上传节点 `{"device_id":"abc123","nodes":[{id,ip,port,proto,country,country_zh,score,ping_ms,speed_bps,config_b64}]}`
- `POST api/vote.php` — 存活投票 `{"device_id":"abc123","votes":[{id,ok}]}`（每设备每节点每小时一条，可覆盖；只收池中已有节点）
- `GET  api/nodes.php` — 返回 `avail_24h`（24h 可用率，无投票为 null）；排序：有投票的按可用率优先，其次确认数/分数
- `GET  api/nodes.php?country=JP&limit=100&min_uploaders=1&max_age_h=48`
- `GET  api/stats.php` — 统计

去重：`id` 主键，重复上传自动合并（`last_seen` 更新）。

计数规则（v2）：
- `upload_count` — 总上传次数，每次上报 +1（含重复）
- `uploader_count` — 独立设备确认数，同一设备重复上报只算 1（`node_devices` 去重）
- `device_id` — 客户端安装时自动生成（12 位 hex），可在 Yu-proxy 设置页修改；老客户端不传时按 `ip:` + IP 归一
- 重复上报时若带了 `config_b64` 会补全之前缺失的配置

## 在线更新

`update.php` — 浏览器打开即用，无需 SSH：

1. 首次访问自动生成管理 token（页面明文显示一次，请保存；之后存在 `data/update_token`）
2. 粘贴 token → 点"从 GitHub 拉取更新"
3. 从 GitHub main 分支下载 zip，白名单覆盖 php/md 文件，**data/ 数据库绝不触碰**，旧文件自动备份到 `data/backup/日期/`

需要 PHP zip 扩展（1Panel → PHP 管理 → 安装扩展）。首页底部也有入口。
