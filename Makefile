# ==============================================================================
# Marriage Station – Database Import Makefile
#
# USAGE
#   make db-import          Import all databases (ms + adminchat)
#   make db-ms              Import only the main 'ms' database
#   make db-adminchat       Import only the 'adminchat' database
#   make db-help            Show all available database targets
#
# CREDENTIALS (override on the command line or set in .env)
#   make db-import DB_HOST=127.0.0.1 DB_USER=myuser DB_PASS=mypassword
#   make db-import MS_DB=marriage ADMINCHAT_DB=marriage_chat
# ==============================================================================

# ── Defaults (read from .env automatically by the shell script) ───────────────
DB_HOST       ?= localhost
DB_PORT       ?= 3306
DB_USER       ?= root
DB_PASS       ?=
MS_DB         ?= ms
ADMINCHAT_DB  ?= adminchat

MIGRATIONS_DIR := www/wwwroot/digitallami.com/migrations
IMPORT_SCRIPT  := $(MIGRATIONS_DIR)/import_databases.sh

# Build the flags string for the import script
_FLAGS := -h $(DB_HOST) -P $(DB_PORT) -u $(DB_USER)
ifneq ($(DB_PASS),)
  _FLAGS += -p $(DB_PASS)
endif
_FLAGS += --ms-db $(MS_DB) --adminchat-db $(ADMINCHAT_DB)

.PHONY: db-import db-ms db-adminchat db-help

## Import all databases (ms + adminchat)
db-import:
	bash $(IMPORT_SCRIPT) $(_FLAGS)

## Import only the main 'ms' database
db-ms:
	bash $(IMPORT_SCRIPT) $(_FLAGS) --skip-adminchat

## Import only the 'adminchat' database
db-adminchat:
	bash $(IMPORT_SCRIPT) $(_FLAGS) --skip-ms

## Show database import help
db-help:
	@echo ""
	@echo "Marriage Station – Database Import Commands"
	@echo "────────────────────────────────────────────────────────────"
	@echo "  make db-import          Import ms + adminchat databases"
	@echo "  make db-ms              Import only ms database"
	@echo "  make db-adminchat       Import only adminchat database"
	@echo ""
	@echo "Override credentials:"
	@echo "  make db-import DB_USER=myuser DB_PASS=secret DB_HOST=localhost"
	@echo ""
	@echo "Override database names:"
	@echo "  make db-import MS_DB=marriage ADMINCHAT_DB=marriage_chat"
	@echo ""
	@echo "SQL files used:"
	@echo "  msfinal/ms.sql                                         (106 tables)"
	@echo "  msfinal/socket-server/sql/chat_tables.sql              (+6 tables)"
	@echo "  $(MIGRATIONS_DIR)/adminchat_schema.sql  (+5 tables)"
	@echo "────────────────────────────────────────────────────────────"
