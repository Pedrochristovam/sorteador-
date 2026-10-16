#!/bin/sh
set -e

mkdir -p storage
chmod -R 775 storage || true

echo "==> Boot Railway"
php database/setup_railway.php

PORT="${PORT:-8080}"
echo "==> Servindo em 0.0.0.0:${PORT}"
exec php -S "0.0.0.0:${PORT}" -t .
