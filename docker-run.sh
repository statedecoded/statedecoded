#!/bin/bash
set -e

cd "$(dirname "$0")"

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example — edit it if needed before re-running."
fi

docker compose up --build -d
docker compose ps
