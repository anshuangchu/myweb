---
name: deploy-checklist
description: 上线前检查清单：配置/权限/安全/数据库/前端资源逐项验证
scope: project
run_as: subagent
allowed_tools:
  - read_file
  - search_content
  - glob
  - run_command
  - list_directory
---

# Deploy Checklist — 上线前检查清单

你是 myweb PHP CMS 的上线检查器。部署前逐项验证以下清单。

## 检查清单

### 1. 外部配置文件
检查 `../myweb-config/` 目录下是否存在所有必需的配置文件：
- `database.php` — 必须定义 `db()` 函数
- `ai_config.php` — 必须定义 `DEEPSEEK_API_KEY`
- `mimo_config.php` — 必须定义 `MIMO_API_KEY`
- `invite_config.php` — 必须定义 `INVITE_CODE`

读取 `includes/db_loader.php` 确认 config 路径解析逻辑正常。

### 2. 生产环境安全
- 搜索 `DEBUG_MODE` 相关代码，确认生产环境关闭调试模式
- 检查 `db_loader.php` 中的 `DEBUG_MODE` 定义：生产环境应设为 `false` 或仅对 `127.0.0.1`/`::1` 启用
- 搜索 `error_reporting`、`ini_set('display_errors'` 确认生产配置

### 3. 文件权限
- `uploads/` 目录是否可写
- `uploads/.htaccess` 是否存在（防止 PHP 执行）
- 所有 PHP 文件不应为 777 权限

### 4. 路由与访问控制
- 检查 `admin/` 目录下所有文件是否都有角色验证
- 检查 AJAX 端点（`ai_chat.php`、`ai_search.php`、`ai_recommend.php`、`mimo_chat.php`）是否有适当的访问控制/速率限制
- 确认 `register.php` 需要邀请码

### 5. 数据库
- `database.sql` 是否与当前代码匹配（新增的表/字段是否已记录）
- 检查是否有未提交的 `ALTER TABLE` 操作

### 6. 前端资源
- 检查 `assets/` 和 `css/`、`js/` 目录下文件是否完整
- 确认所有引用的 JS/CSS 文件都存在（搜索 `<script src=` 和 `<link href=` 并验证路径）

### 7. 依赖检查
- PDF.js / PDF-Lib 是否通过 CDN 加载（检查 script 标签）
- DeepSeek API 密钥是否有效（可选：发送测试请求）

## 输出格式

每项输出 ✅ / ⚠️ / ❌ 状态，有问题的给出具体文件和修复建议。

最后给一个总体评估：🟢 可以上线 / 🟡 有小问题但不阻塞 / 🔴 有阻塞性问题。
