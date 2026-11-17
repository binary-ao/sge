#!/bin/bash
# Publicação automática do SIGE Bengo no servidor de produção (sge.binary.ao).
#
# Fluxo: código local -> GitHub (ramo main) -> servidor (este script faz o pull).
# O cron da conta cPanel executa-o a cada 2 minutos; só actua quando há commits novos no GitHub.
#
#   deploy.sh           publica apenas se houver alterações
#   deploy.sh --forcar  republica mesmo sem alterações (limpa e refaz as caches)
#
# Antes de aplicar migrações novas faz uma cópia de segurança da base de dados (sige:backup).

set -euo pipefail

APP="${SIGE_APP:-$HOME/sge}"
PHP="${SIGE_PHP:-/opt/cpanel/ea-php82/root/usr/bin/php}"
COMPOSER="${SIGE_COMPOSER:-$HOME/bin/composer.phar}"
RAMO="${SIGE_RAMO:-main}"
LOG="$HOME/logs/deploy.log"

mkdir -p "$(dirname "$LOG")"
exec 9>"$HOME/.deploy.lock"
flock -n 9 || exit 0    # já existe uma publicação em curso

cd "$APP"
git fetch --quiet origin "$RAMO"

ACTUAL=$(git rev-parse HEAD)
NOVO=$(git rev-parse "origin/$RAMO")
if [ "$ACTUAL" = "$NOVO" ] && [ "${1:-}" != "--forcar" ]; then
    exit 0
fi

exec >>"$LOG" 2>&1
echo "=== $(date '+%Y-%m-%d %H:%M:%S') publicação ${ACTUAL:0:7} -> ${NOVO:0:7}"

EM_MANUTENCAO=0
terminar() {
    estado=$?
    if [ "$EM_MANUTENCAO" = 1 ]; then "$PHP" artisan up || true; fi
    if [ $estado -ne 0 ]; then echo "!!! FALHOU (código $estado). O site continua na versão anterior ou deve ser verificado."; fi
}
trap terminar EXIT

MUDOU=$(git diff --name-only "$ACTUAL" "$NOVO")

"$PHP" artisan down --retry=15 || true
EM_MANUTENCAO=1

git merge --ff-only "origin/$RAMO"

if [ "$ACTUAL" != "$NOVO" ] && ! echo "$MUDOU" | grep -q '^composer\.lock$'; then
    echo "composer.lock sem alterações: dependências mantidas"
else
    "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress
fi

if echo "$MUDOU" | grep -q '^database/migrations/'; then
    echo "Migrações novas: cópia de segurança antes de migrar"
    "$PHP" artisan sige:backup
fi
"$PHP" artisan migrate --force

"$PHP" artisan optimize:clear
"$PHP" artisan optimize

"$PHP" artisan up
EM_MANUTENCAO=0
echo "=== concluída: $(git log --oneline -1)"
