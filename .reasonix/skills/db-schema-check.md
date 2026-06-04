---
name: db-schema-check
description: 对比 database.sql 与实际数据库表结构差异，生成 ALTER 建议
scope: project
run_as: subagent
allowed_tools:
  - read_file
  - search_content
  - glob
  - run_command
---

# DB Schema Check — 数据库结构校验

你是 myweb PHP CMS 的数据库结构校验器。对比 `database.sql` 与实际运行数据库的表结构差异。

## 步骤

1. 读取 `database.sql`，解析所有 CREATE TABLE 语句，提取：
   - 表名
   - 字段名、类型、是否 NULL、默认值
   - 索引（PRIMARY KEY、KEY）
   - 外键约束

2. 如果 MySQL 可连接（通过项目中 `includes/db_loader.php` 引用的 config），尝试连接并执行：
   - `SHOW TABLES` — 列出所有表
   - `SHOW CREATE TABLE <table>` — 对每个表获取实际结构
   - `SHOW INDEX FROM <table>` — 索引信息

3. 对比差异，输出：
   - 🔴 **缺失的表** — 在 schema.sql 中有但数据库中没有
   - 🟡 **多余的字段** — 数据库中有但 schema.sql 中没有（可能是手动加的）
   - 🔴 **缺失的字段** — schema.sql 中有但数据库中没有
   - 🟠 **类型不匹配** — 字段类型/长度不一致
   - ⚪ **索引差异** — 索引缺失或多余

4. 对每个差异生成修复建议（ALTER TABLE 语句）。

## 输出格式

### 表结构对比

| 表名 | 状态 | 差异数 |
|------|------|--------|

### 差异详情

**`articles` 表：**
- ❌ 缺失字段 `summary` (varchar(500)) → `ALTER TABLE articles ADD COLUMN summary varchar(500) DEFAULT '';`
- ⚠️ 类型不匹配 `views` — schema: int(11), actual: bigint(20)

如果无法连接数据库，输出 schema.sql 的表结构摘要作为参考。
