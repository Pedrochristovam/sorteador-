#!/bin/sh
# Não usa set -e: o setup do banco não pode impedir o PHP de subir.

mkdir -p storage
chmod -R 775 storage || true

echo "==> Boot Railway"
echo "    PORT=${PORT:-8080} DB_ENABLED=${DB_ENABLED:-unset}"
echo "    DB_HOST=${DB_HOST:-${MYSQLHOST:-unset}}"

if [ "${DB_ENABLED}" = "true" ] || [ "${DB_ENABLED}" = "1" ]; then
  php database/setup_railway.php || echo "AVISO: setup do banco falhou — app sobe mesmo assim (verifique o MySQL e as Variables)."
else
  echo "    DB_ENABLED não é true — pulando setup (modo arquivo/mocks)."
fi

PORT="${PORT:-8080}"
echo "==> Servindo em 0.0.0.0:${PORT}"
exec php -S "0.0.0.0:${PORT}" -t .
