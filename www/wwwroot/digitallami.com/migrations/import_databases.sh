#!/usr/bin/env bash
# ==============================================================================
# import_databases.sh
#
# Imports all databases required by Marriage Station in one command.
#
# USAGE
# ─────
#   bash import_databases.sh [OPTIONS]
#
# OPTIONS
#   -h | --host     MySQL host          (default: localhost)
#   -P | --port     MySQL port          (default: 3306)
#   -u | --user     MySQL user          (default: root)
#   -p | --pass     MySQL password      (default: empty)
#   --ms-db         Main DB name        (default: ms)
#   --adminchat-db  Admin-chat DB name  (default: adminchat)
#   --skip-ms       Skip importing the main ms database
#   --skip-adminchat Skip importing the adminchat database
#   --help          Show this help
#
# EXAMPLES
#   # Minimal – reads credentials from .env (recommended)
#   bash import_databases.sh
#
#   # Explicit credentials
#   bash import_databases.sh -u myuser -p mypassword
#
#   # Custom DB names
#   bash import_databases.sh --ms-db marriage --adminchat-db marriage_chat
#
#   # Import only the adminchat database
#   bash import_databases.sh --skip-ms
#
# NOTES
#   • The script auto-detects the project root (two levels above this file).
#   • Credentials are read from .env first, then overridden by CLI flags.
#   • All SQL files are imported with --force so existing tables survive
#     duplicate-key errors on seed rows.
# ==============================================================================

set -euo pipefail

# ── Resolve paths ─────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Migrations dir  : www/wwwroot/digitallami.com/migrations/
# Project root    : ../../.. relative to migrations/
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
WWW_ROOT="$PROJECT_ROOT/www/wwwroot/digitallami.com"
SOCKET_SQL="$PROJECT_ROOT/msfinal/socket-server/sql/chat_tables.sql"
MS_SQL="$PROJECT_ROOT/msfinal/ms.sql"
ADMINCHAT_SQL="$SCRIPT_DIR/adminchat_schema.sql"

# ── Defaults ──────────────────────────────────────────────────────────────────
DB_HOST="localhost"
DB_PORT="3306"
DB_USER="root"
DB_PASS=""
MS_DB="ms"
ADMINCHAT_DB="adminchat"
SKIP_MS=false
SKIP_ADMINCHAT=false

# ── Load .env if present ──────────────────────────────────────────────────────
ENV_FILE="$WWW_ROOT/.env"
if [[ -f "$ENV_FILE" ]]; then
    echo "📄  Loading credentials from $ENV_FILE"
    while IFS='=' read -r key value; do
        # Strip comments and blank lines
        [[ "$key" =~ ^[[:space:]]*# ]] && continue
        [[ -z "${key// }" ]] && continue
        # Strip surrounding quotes from value
        value="${value#\"}" ; value="${value%\"}"
        value="${value#\'}" ; value="${value%\'}"
        case "$key" in
            DB_HOST)           DB_HOST="$value"       ;;
            DB_PORT)           DB_PORT="$value"       ;;
            DB_USER)           DB_USER="$value"       ;;
            DB_PASS)           DB_PASS="$value"       ;;
            DB_NAME)           MS_DB="$value"         ;;
            ADMINCHAT_DB_HOST) DB_HOST="$value"       ;;
            ADMINCHAT_DB_NAME) ADMINCHAT_DB="$value"  ;;
            ADMINCHAT_DB_USER) DB_USER="$value"       ;;
            ADMINCHAT_DB_PASS) DB_PASS="$value"       ;;
        esac
    done < "$ENV_FILE"
fi

# ── Parse CLI arguments ───────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        -h|--host)           DB_HOST="$2";       shift 2 ;;
        -P|--port)           DB_PORT="$2";       shift 2 ;;
        -u|--user)           DB_USER="$2";       shift 2 ;;
        -p|--pass)           DB_PASS="$2";       shift 2 ;;
        --ms-db)             MS_DB="$2";         shift 2 ;;
        --adminchat-db)      ADMINCHAT_DB="$2";  shift 2 ;;
        --skip-ms)           SKIP_MS=true;       shift   ;;
        --skip-adminchat)    SKIP_ADMINCHAT=true; shift  ;;
        --help)
            sed -n '2,50p' "$0"   # print the usage block at the top
            exit 0
            ;;
        *)
            echo "❌  Unknown option: $1"
            echo "    Run with --help for usage."
            exit 1
            ;;
    esac
done

# ── Build mysql / mysqldump option string ─────────────────────────────────────
MYSQL_OPTS=(-h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER")
if [[ -n "$DB_PASS" ]]; then
    MYSQL_OPTS+=("-p$DB_PASS")
fi

# ── Helper: run mysql ─────────────────────────────────────────────────────────
run_mysql() {
    mysql "${MYSQL_OPTS[@]}" "$@"
}

# ── Verify mysql binary is available ─────────────────────────────────────────
if ! command -v mysql &>/dev/null; then
    echo "❌  'mysql' command not found."
    echo "    Install MySQL client:  sudo apt install mysql-client"
    echo "                       or: sudo yum install mysql"
    exit 1
fi

# ── Test connection ───────────────────────────────────────────────────────────
echo ""
echo "🔌  Testing MySQL connection to $DB_HOST:$DB_PORT as '$DB_USER'…"
if ! run_mysql -e "SELECT 1;" &>/dev/null; then
    echo "❌  Cannot connect to MySQL. Check your credentials."
    exit 1
fi
echo "✅  Connection OK"
echo ""

# ══════════════════════════════════════════════════════════════════════════════
# 1.  Main  'ms'  database
# ══════════════════════════════════════════════════════════════════════════════
if [[ "$SKIP_MS" == false ]]; then
    if [[ ! -f "$MS_SQL" ]]; then
        echo "⚠️   ms.sql not found at: $MS_SQL"
        echo "    Skipping main database import."
    else
        echo "─────────────────────────────────────────────────────────────"
        echo "📦  Importing main database  →  '$MS_DB'"
        echo "    File : $MS_SQL"
        echo "    Size : $(du -sh "$MS_SQL" | cut -f1)"
        echo ""

        # Create database if it does not exist
        run_mysql -e "CREATE DATABASE IF NOT EXISTS \`$MS_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

        # Import the dump (--force continues past duplicate-key / other errors)
        if run_mysql --force "$MS_DB" < "$MS_SQL"; then
            echo "✅  '$MS_DB' imported successfully"
        else
            echo "⚠️   Import finished with warnings (non-fatal errors may appear above)."
        fi
        echo ""

        # Run socket-server chat tables (idempotent; safe to rerun)
        if [[ -f "$SOCKET_SQL" ]]; then
            echo "🔌  Applying socket-server chat tables to '$MS_DB'…"
            if run_mysql --force "$MS_DB" < "$SOCKET_SQL"; then
                echo "✅  Socket-server chat tables applied"
            else
                echo "⚠️   Socket-server tables finished with warnings."
            fi
            echo ""
        fi
    fi
else
    echo "⏭️   Skipping main 'ms' database (--skip-ms)"
    echo ""
fi

# ══════════════════════════════════════════════════════════════════════════════
# 2.  Admin-chat  'adminchat'  database
# ══════════════════════════════════════════════════════════════════════════════
if [[ "$SKIP_ADMINCHAT" == false ]]; then
    if [[ ! -f "$ADMINCHAT_SQL" ]]; then
        echo "⚠️   adminchat_schema.sql not found at: $ADMINCHAT_SQL"
        echo "    Skipping adminchat database import."
    else
        echo "─────────────────────────────────────────────────────────────"
        echo "📦  Importing admin-chat database  →  '$ADMINCHAT_DB'"
        echo "    File : $ADMINCHAT_SQL"
        echo ""

        # Create database if it does not exist
        run_mysql -e "CREATE DATABASE IF NOT EXISTS \`$ADMINCHAT_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

        # Import the schema (idempotent – uses CREATE TABLE IF NOT EXISTS)
        if run_mysql --force "$ADMINCHAT_DB" < "$ADMINCHAT_SQL"; then
            echo "✅  '$ADMINCHAT_DB' imported successfully"
        else
            echo "⚠️   Import finished with warnings (non-fatal errors may appear above)."
        fi
        echo ""
    fi
else
    echo "⏭️   Skipping adminchat database (--skip-adminchat)"
    echo ""
fi

# ══════════════════════════════════════════════════════════════════════════════
# Done
# ══════════════════════════════════════════════════════════════════════════════
echo "═════════════════════════════════════════════════════════════"
echo "🎉  Database import complete!"
echo ""
echo "   Main DB      : $MS_DB"
echo "   Admin-chat DB: $ADMINCHAT_DB"
echo ""
echo "   Default admin credentials (ms):"
echo "     Email   : admin@ms.com"
echo "     Password: Admin@123"
echo ""
echo "   Default agent credentials (adminchat):"
echo "     Email   : admin@example.com"
echo "     Password: admin123"
echo "═════════════════════════════════════════════════════════════"
