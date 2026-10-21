#!/usr/bin/env bash
# WP-CLI w lokalnym WordPressie (docker-compose.yml). Użycie z korzenia repo:  skrypty/wp.sh plugin list
# Kontener widzi: /import/dane (= dane/eksport, tylko odczyt) i /import/skrypty (= skrypty/import).
# MSYS_NO_PATHCONV: Git Bash na Windows przerabia /import/... na C:/Program Files/Git/import/...
export MSYS_NO_PATHCONV=1
exec docker compose run --rm -T wpcli wp "$@"
