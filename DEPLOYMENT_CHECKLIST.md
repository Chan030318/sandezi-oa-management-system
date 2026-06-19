# 三德子 OA 系统 — 部署检查清单

**版本：** v1.0  
**生成日期：** 2026-06-19  
**系统版本：** Commit `34764fd`  
**适用场景：** 内部服务器首次部署 / 生产环境上线

---

> 按顺序逐项执行，完成后在 `[ ]` 内打 `x`。

---

## 第一步：服务器环境确认

- [ ] PHP 版本 ≥ 7.4（推荐 8.1+）：`php -v`
- [ ] MySQL 版本 ≥ 5.7（推荐 8.0+）：`mysql --version`
- [ ] Apache / Nginx 已安装并运行
- [ ] PHP 扩展确认已启用：`pdo`、`pdo_mysql`、`mbstring`、`openssl`、`session`
- [ ] 服务器时区设置正确（建议 `Asia/Kuala_Lumpur`）：`date` 或 php `date_default_timezone_get()`

---

## 第二步：数据库准备

- [ ] 创建 MySQL 数据库用户（专用账号，不使用 root）

  ```sql
  CREATE USER 'sandezi_user'@'localhost' IDENTIFIED BY '强密码请自行设置';
  GRANT ALL PRIVILEGES ON sandezi_oa_plus.* TO 'sandezi_user'@'localhost';
  FLUSH PRIVILEGES;
  ```

- [ ] 执行部署 SQL 脚本，初始化所有表结构与种子数据

  ```bash
  mysql -u root -p < deploy_production.sql
  ```

- [ ] 确认所有表已创建（应有 14 张表）

  ```sql
  USE sandezi_oa_plus;
  SHOW TABLES;
  ```

  预期表清单：
  ```
  announcements       audit_logs          departments
  device_borrows      device_maintenance  devices
  employees           leaves              login_logs
  schedules           shifts              users
  venue_bookings      venues
  ```

- [ ] 确认 Admin 账号已存在

  ```sql
  SELECT id, name, email, role FROM users;
  ```

---

## 第三步：config.php 配置

- [ ] 复制示例配置文件

  ```bash
  cp config.example.php config.php
  ```

- [ ] 编辑 `config.php`，填入实际数据库信息

  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'sandezi_oa_plus');
  define('DB_USER', 'sandezi_user');     // 步骤二创建的专用用户
  define('DB_PASS', '你设置的强密码');
  define('APP_NAME', '三德子 OA 管理系统');
  define('APP_SUBTITLE', '内部管理系统');
  ```

- [ ] 确认 `config.php` 已在 `.gitignore` 中（**绝对不能提交到 Git**）

  ```bash
  git check-ignore -v config.php
  # 预期输出：.gitignore:X:config.php  config.php
  ```

- [ ] 测试数据库连接

  ```bash
  php -r "require 'config.php'; require 'db.php'; echo '连接成功';"
  ```

---

## 第四步：文件权限设置

- [ ] 设置项目目录所有者为 Web 服务器用户（通常为 `www-data` 或 `apache`）

  ```bash
  chown -R www-data:www-data /var/www/sandezi-oa/
  ```

- [ ] 设置目录权限（目录 755，文件 644）

  ```bash
  find /var/www/sandezi-oa/ -type d -exec chmod 755 {} \;
  find /var/www/sandezi-oa/ -type f -exec chmod 644 {} \;
  ```

- [ ] 确认 `config.php` 权限为 640（只有 owner 和 group 可读）

  ```bash
  chmod 640 config.php
  ```

- [ ] 确认日志目录（如有）Web 用户可写

- [ ] 检查没有多余的 `.git`、`.claude`、`*.md` 暴露在 Web 根目录
  > 如使用 Apache，可在 `.htaccess` 中屏蔽：
  ```apache
  <FilesMatch "\.(md|sql|json|lock|gitignore)$">
      Require all denied
  </FilesMatch>
  <DirectoryMatch "^.*/(\.git|\.claude)/">
      Require all denied
  </DirectoryMatch>
  ```

---

## 第五步：HTTPS 配置

- [ ] 申请 SSL 证书（推荐免费 Let's Encrypt）

  ```bash
  # 安装 Certbot（Ubuntu/Debian）
  sudo apt install certbot python3-certbot-apache
  sudo certbot --apache -d yourdomain.com
  ```

- [ ] 强制 HTTP → HTTPS 跳转（Apache .htaccess）

  ```apache
  RewriteEngine On
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```

- [ ] 验证证书有效且 HTTPS 正常访问

- [ ] 验证 Session Cookie 带有 `Secure` 标志（浏览器 DevTools > Cookies）
  > 系统代码已自动判断 HTTPS 并设置 Secure，无需手动修改

- [ ] 配置证书自动续期

  ```bash
  sudo certbot renew --dry-run
  ```

---

## 第六步：默认密码修改（上线后第一件事）

- [ ] 用 `admin@sandezi.com` / `SdzAdmin2026!` 登录系统
- [ ] 立即进入【修改密码】，改为强密码（≥ 12 位，含大小写+数字+符号）
- [ ] **新密码妥善保存，勿写在此文件或 Git 中**
- [ ] 新增正式 Manager 账号，设置强密码（测试账号密码需同步更新）
- [ ] 新增员工账号，通知员工第一次登录后自行修改密码
- [ ] 确认 `TEST_ACCOUNTS.md` 中的测试账号已停用或密码已更改

---

## 第七步：登录速率限制验证

- [ ] 故意输错密码 5 次，确认出现 10 分钟锁定提示
- [ ] 等待 10 分钟后，确认自动解锁
- [ ] 确认【登录日志】中所有失败尝试均有记录（邮箱 + IP + 时间）

---

## 第八步：备份策略

- [ ] 配置 MySQL 每日自动备份

  ```bash
  # /etc/cron.d/sandezi-backup
  0 3 * * * www-data mysqldump -u sandezi_user -p'密码' sandezi_oa_plus \
    | gzip > /backups/sandezi_oa_$(date +\%Y\%m\%d).sql.gz
  ```

- [ ] 备份目录权限设置正确（仅 root / 备份用户可访问）

  ```bash
  mkdir -p /backups
  chmod 700 /backups
  ```

- [ ] 测试备份恢复流程（重要！确保备份可用）

  ```bash
  gunzip < /backups/sandezi_oa_20260619.sql.gz | mysql -u root -p sandezi_oa_plus
  ```

- [ ] 设置备份保留策略（建议保留 30 天）

  ```bash
  # 在 cron 中加入清理旧备份
  find /backups/ -name "sandezi_oa_*.sql.gz" -mtime +30 -delete
  ```

- [ ] 考虑异地备份（上传到云存储或另一台服务器）

---

## 第九步：上线前最终检查

- [ ] 浏览器访问系统，登录页面正常显示
- [ ] Admin 登录成功，Dashboard 数据正常显示
- [ ] 所有侧边栏链接均可正常访问（无 404）
- [ ] 新增一条员工记录，保存成功，操作日志有记录
- [ ] 新增一条场地预约，冲突检测功能正常
- [ ] 导出一份排班 CSV，Excel 打开中文不乱码
- [ ] 检查服务器错误日志，无异常报错

  ```bash
  tail -f /var/log/apache2/error.log
  # 或
  tail -f /var/log/nginx/error.log
  ```

- [ ] 确认 PHP 错误不显示在页面上（`display_errors = Off`）

  ```ini
  ; php.ini 生产环境配置
  display_errors = Off
  log_errors = On
  error_log = /var/log/php_errors.log
  ```

---

## 第十步：通知与文档

- [ ] 向测试参与人员发送系统访问链接和测试账号（参考 `TEST_ACCOUNTS.md`）
- [ ] 分发 `USER_TESTING_CHECKLIST.md` 给测试人员
- [ ] 设置测试反馈渠道（如：企业微信群 / 邮件）
- [ ] 确认测试周期（建议 1～2 周）
- [ ] 测试期间保持开发人员联络畅通，及时处理问题反馈

---

## 快速参考：重要文件说明

| 文件 | 说明 | Git 状态 |
|---|---|---|
| `config.php` | 数据库连接配置（含密码）| ❌ 已 gitignore，不可提交 |
| `config.example.php` | 配置文件模板（无密码）| ✅ 已提交 |
| `deploy_production.sql` | 数据库初始化脚本 | ✅ 已提交 |
| `TEST_ACCOUNTS.md` | 测试账号说明 | ✅ 已提交（无实际密码）|
| `USER_TESTING_CHECKLIST.md` | 测试清单 | ✅ 已提交 |
| `INTERNAL_TESTING_REPORT.md` | 内部测试说明报告 | ✅ 已提交 |

---

*本文件由开发团队生成，v1.0，2026-06-19*  
*执行过程如有疑问请联系开发负责人。*
