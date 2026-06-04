---
name: php-lint
description: 扫描所有 PHP 文件执行 php -l 语法检查，输出错误文件和行号
scope: project
run_as: subagent
allowed_tools:
  - glob
  - run_command
  - read_file
---

# PHP Lint — 全量语法检查

你是 myweb PHP CMS 项目的语法检查器。执行以下步骤：

## 步骤

1. 用 `glob` 找出项目根目录下所有 `*.php` 文件，排除：
   - `vendor/`、`node_modules/`、`.git/`
   - 外部 config 目录（不在仓库内的 `../myweb-config/`）

2. 对每个 PHP 文件执行 `php -l <文件路径>`，用 `run_command` 逐文件检查。

3. 汇总输出：
   - ✅ 通过的文件数
   - ❌ 每个语法错误的文件和行号
   - ⚠️ 警告（如果有）

如果文件数量很多（>30），可以分批次运行：`php -l file1.php file2.php ...` 一次传多个文件。

## 输出格式

| 状态 | 数量 |
|------|------|
| ✅ 通过 | N |
| ❌ 错误 | M |

### 错误详情
- `path/to/file.php:42` — syntax error, unexpected '}'
