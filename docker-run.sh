#!/bin/bash
set -e

cd "$(dirname "$0")"

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example — edit it if needed before re-running."
fi

docker compose up --build -d
docker compose ps
echo ""
echo "Site: http://localhost:8080/"
echo "Admin: http://localhost:8080/admin/  (user: admin  pass: admin)"
