#!/usr/bin/env bash
# Sobe os dois servidores do ELOS no seu computador (desenvolvimento).
# As variáveis do banco vêm de backend/.env (copie de backend/.env.example).
# O PHP continua sem ler .env: quem lê é este script, antes de subir.
set -euo pipefail
cd "$(dirname "$0")"

if [[ ! -f backend/.env ]]; then
    echo "Crie backend/.env a partir de backend/.env.example e preencha DB_*." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
. backend/.env
set +a

php -S 127.0.0.1:8000 -t backend/public &
BACKEND=$!
trap 'kill $BACKEND 2>/dev/null' EXIT

echo "Backend:  http://127.0.0.1:8000"
echo "Frontend: http://127.0.0.1:8081   (Ctrl+C para parar os dois)"
php -S 127.0.0.1:8081 -t frontend
