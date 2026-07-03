# myweb — 安双初 个人 CMS

基于 PHP 8 + MySQL 的暗色主题博客系统，无框架，零依赖。

## 技术栈
- PHP 8+ / MariaDB / Nginx
- 纯 CSS 暗色主题（CSS 自定义属性）
- Vanilla JavaScript（零框架）

## 功能模块

| 模块 | 说明 |
|------|------|
| 文章系统 | 发布/编辑/分类/标签/封面图/AI 辅助写作 |
| 文件管理 | 上传/预览/重命名/编辑文本/Office 解析 |
| 用户系统 | 注册(邀请码)/登录/角色权限/个人设置/头像 |
| 好友消息 | 好友请求/验证/实时聊天/图片文件发送 |
| AI 助手 | DeepSeek 对话/搜索增强/文章推荐/格式化 |
| 搜索 | FULLTEXT 全文搜索 + LIKE 回退 |
| 管理后台 | 控制台/文章/文件/分类/标签/用户/设置 |

## 目录结构

```
myweb/
├── css/style.css          # 全局样式 (~3900行)
├── js/
│   ├── script.js          # 主脚本 (AI聊天/拖拽/主题)
│   ├── editor.js          # 文章编辑器
│   ├── search.js          # AI搜索增强
│   └── files.js           # 文件视图切换
├── includes/
│   ├── header.php         # 全局头部(导航/用户/主题)
│   ├── footer.php         # 全局页脚(友链/版权/AI浮窗)
│   ├── db_loader.php      # 数据库连接
│   ├── helpers.php        # 工具函数
│   ├── article_service.php # 文章服务
│   ├── user_service.php   # 用户设置服务
│   ├── social_service.php # 好友/消息服务
│   ├── search_service.php # 搜索服务(FULLTEXT)
│   ├── file_service.php   # 文件服务
│   ├── ai_service.php     # AI(DeepSeek)服务
│   ├── chat_widget.php    # AI对话浮窗
│   └── admin_sidebar.php  # 管理侧栏
├── admin/                 # 管理后台
├── uploads/               # 用户上传文件
└── database.sql           # 数据库结构
```

## 外部配置
`../myweb-config/` 目录（web 根目录外）：
- `database.php` — PDO 连接 + CSRF/角色函数
- `ai_config.php` — DeepSeek API 密钥
- `invite_config.php` — 注册邀请码

## 设计系统
- 暗色主题 `#060b14` 底色
- accent 色 `#3b82f6`（海洋蓝）
- 流动光晕卡片系统 (`.glow-card`)
- CSS 自定义属性令牌体系

## 部署
```bash
tar -czf deploy.tar.gz --exclude=uploads --exclude=.git --exclude=.reasonix -C . .
scp deploy.tar.gz root@SERVER:/tmp/
ssh root@SERVER "tar -xzf /tmp/deploy.tar.gz -C /var/www/myweb/ --overwrite && chown -R www-data:www-data /var/www/myweb && systemctl restart php8.3-fpm"
```
