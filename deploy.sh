#!/bin/bash
# ============================================================
# myweb SCP 部署脚本
# 用法: bash deploy.sh [--db] [--full]
#   --db   同时部署数据库
#   --full 完整部署（含 uploads 目录）
# ============================================================
set -e

# ---- 服务器配置 ----
SERVER="root@47.96.109.189"
SSH_KEY="C:/Users/Lenovo/Desktop/_02_编程_AI/love456258.pem"
WEB_ROOT="/var/www/myweb"
SSH="ssh -i \"$SSH_KEY\" -o StrictHostKeyChecking=no"
SCP="scp -i \"$SSH_KEY\" -o StrictHostKeyChecking=no"

# ---- 本地配置 ----
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
DEPLOY_PKG="/tmp/myweb-deploy-$(date +%Y%m%d-%H%M%S).tar.gz"
DB_DUMP="/tmp/myweb-db-$(date +%Y%m%d-%H%M%S).sql.gz"

# ---- 颜色输出 ----
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[DEPLOY]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ---- 参数解析 ----
DO_DB=false
DO_FULL=false
for arg in "$@"; do
    case $arg in
        --db)   DO_DB=true ;;
        --full) DO_FULL=true ;;
    esac
done

log "开始部署 myweb → $SERVER:$WEB_ROOT"

# ============================================================
# 步骤 1: 打包项目
# ============================================================
log "步骤 1/5: 打包项目文件..."

cd "$PROJECT_DIR"

# 构建排除列表
EXCLUDES=(
    --exclude='.git'
    --exclude='.claude'
    --exclude='.reasonix'
    --exclude='.gitignore'
    --exclude='.vscode'
    --exclude='.idea'
    --exclude='*.tmp'
    --exclude='*.tar.gz'
    --exclude='desktop.ini'
    --exclude='memory.md'
    --exclude='CLAUDE.md'
    --exclude='deploy.sh'
    --exclude='deploy-db.sh'
    --exclude='网站总结报告.html'
    --exclude='config'
)

# 非完整模式排除 uploads
if [ "$DO_FULL" != true ]; then
    EXCLUDES+=(--exclude='uploads/*')
    log "跳过 uploads 目录（使用 --full 可包含）"
fi

tar -czf "$DEPLOY_PKG" "${EXCLUDES[@]}" .

PKG_SIZE=$(du -h "$DEPLOY_PKG" | cut -f1)
log "打包完成: $DEPLOY_PKG ($PKG_SIZE)"

# ============================================================
# 步骤 2: 数据库导出（可选）
# ============================================================
if [ "$DO_DB" = true ]; then
    log "步骤 2/6: 导出本地数据库..."

    if ! command -v mysqldump &>/dev/null; then
        # 尝试 XAMPP 路径
        MYSQLDUMP="C:/xampp/mysql/bin/mysqldump.exe"
    else
        MYSQLDUMP="mysqldump"
    fi

    $MYSQLDUMP -u root --password='@Love456258' myweb \
        --no-tablespaces \
        --add-drop-table \
        | gzip > "$DB_DUMP"

    DB_SIZE=$(du -h "$DB_DUMP" | cut -f1)
    log "数据库导出完成: $DB_DUMP ($DB_SIZE)"

    # 上传数据库
    log "上传数据库到服务器..."
    eval "$SCP \"$DB_DUMP\" \"$SERVER:/tmp/\""

    # 导入数据库（先备份服务器的）
    log "服务器端导入数据库..."
    eval "$SSH \"$SERVER\" bash -s" << 'DBSCRIPT'
        # 备份服务器现有数据库
        BACKUP="/tmp/myweb-db-backup-$(date +%Y%m%d-%H%M%S).sql.gz"
        mysqldump myweb | gzip > "$BACKUP"
        echo "服务器数据库已备份到: $BACKUP"

        # 导入新数据库
        DB_FILE=$(ls -t /tmp/myweb-db-*.sql.gz | head -1)
        gunzip -c "$DB_FILE" | mysql myweb
        echo "数据库导入成功"
DBSCRIPT
    rm -f "$DB_DUMP"
fi

# ============================================================
# 步骤 3: SCP 上传到服务器
# ============================================================
log "步骤 3/5: SCP 上传到服务器..."
eval "$SCP \"$DEPLOY_PKG\" \"$SERVER:/tmp/\""

# ============================================================
# 步骤 4: 服务器端解压和配置
# ============================================================
log "步骤 4/5: 服务器端解压和配置..."

eval "$SSH \"$SERVER\" bash -s" << 'SERVERSCRIPT'
set -e
PKG=$(ls -t /tmp/myweb-deploy-*.tar.gz | head -1)
WEB_ROOT="/var/www/myweb"

echo "解压: $PKG → $WEB_ROOT"

# 备份现有 uploads（如果存在）
if [ -d "$WEB_ROOT/uploads" ]; then
    BACKUP="/tmp/myweb-uploads-backup-$(date +%Y%m%d-%H%M%S)"
    cp -r "$WEB_ROOT/uploads" "$BACKUP" 2>/dev/null || true
    echo "uploads 已备份到: $BACKUP"
fi

# 删除旧文件（保留 uploads 和 config）
find "$WEB_ROOT" -mindepth 1 -maxdepth 1 \
    -not -name uploads \
    -not -name config \
    -exec rm -rf {} + 2>/dev/null || true

# 解压新文件
tar -xzf "$PKG" -C "$WEB_ROOT"

# 恢复 uploads 中的新上传文件
if [ -d "$BACKUP" ]; then
    # 如果部署包不包含 uploads，恢复备份
    if [ ! -d "$WEB_ROOT/uploads" ] || [ -z "$(ls -A "$WEB_ROOT/uploads" 2>/dev/null)" ]; then
        mkdir -p "$WEB_ROOT/uploads"
        cp -r "$BACKUP"/* "$WEB_ROOT/uploads/" 2>/dev/null || true
    fi
fi

# 设置正确权限
chown -R www-data:www-data "$WEB_ROOT"
chmod -R 755 "$WEB_ROOT"
chmod -R 775 "$WEB_ROOT/uploads" 2>/dev/null || true

# 清理临时文件
rm -f "$PKG"

echo "文件解压完成，权限已设置"
SERVERSCRIPT

# ============================================================
# 步骤 5: 重启服务
# ============================================================
log "步骤 5/5: 重启 PHP-FPM..."
eval "$SSH \"$SERVER\" 'systemctl restart php8.3-fpm && echo PHP-FPM 已重启'"

# ---- 清理本地临时文件 ----
rm -f "$DEPLOY_PKG"

echo ""
log "============================================"
log "部署完成！"
log "网站地址: http://47.96.109.189"
log "============================================"
