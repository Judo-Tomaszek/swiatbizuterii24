#!/usr/bin/env bash
# Polecenie na serwerze produkcyjnym (cal.pl) — ZAWSZE w katalogu sklepu swiatbizuterii24.pl/public_html.
# Na tym koncie hostingu są też inne strony: nigdy nie wychodź poza ~/domains/swiatbizuterii24.pl.
# Dane logowania z .env: SSH_HOST, SSH_PORT, SSH_LOGIN, SSH_PASSWORD, FTP_DOMAIN_ADRESS (=/domains/swiatbizuterii24.pl).
# Wymaga PuTTY (plink). Użycie z korzenia repo:  skrypty/serwer.sh 'wp option get home'
cd "$(dirname "$0")/.." || exit 1
set -a; . ./.env; set +a
KLUCZ=$(ssh-keygen -lf <(ssh-keyscan -t ed25519 -p "$SSH_PORT" "$SSH_HOST" 2>/dev/null) -E sha256 | awk '{print $2}')
exec "/c/Program Files/PuTTY/plink" -batch -ssh -P "$SSH_PORT" -l "$SSH_LOGIN" -pw "$SSH_PASSWORD" -hostkey "$KLUCZ" "$SSH_HOST" \
	"cd ~${FTP_DOMAIN_ADRESS}/public_html && [ \"\$(wp option get home)\" = https://swiatbizuterii24.pl ] || { echo 'to nie katalog sklepu'; exit 1; }; $1"
