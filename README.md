# Yu-pool 公共节点池 API

极简 PHP + SQLite，无框架依赖，PHP 7.4+ / 8.x 均可。

## 部署

1. 上传整个目录到 VPS，如 `/var/www/pool`
2. 确保 `data/` 可写：`mkdir -p data && chmod 755 data`
3. 需要 PHP SQLite3 扩展：`php -m | grep -i sqlite`
4. Nginx/Apache 把域名指向本目录，`api/` 下的 php 文件即接口
5. 加 cron 每天清理：`0 3 * * * php /var/www/pool/cleanup.php >> /var/log/pool-clean.log 2>&1`

## 接口

- `POST api/upload.php` — 上传节点 `{"nodes":[{id,ip,port,proto,country,country_zh,score,ping_ms,speed_bps}]}`
- `GET  api/nodes.php?country=JP&limit=100&min_uploaders=1&max_age_h=48`
- `GET  api/stats.php` — 统计

去重：`id` 主键，重复上传自动合并（`last_seen` 更新，`uploader_count+1`）。
