#!/bin/bash
set -e

cd "$(dirname "$0")"

if [ ! -f ../.env ]; then
    cp ../.env.example ../.env
    echo "Created .env from .env.example — edit it if needed before re-running."
fi

if [ ! -f ../htdocs/themes/StateDecoded2013/static/js/vendor/jquery.min.js ]; then
    echo "Front-end assets missing — running npm install && npm run build..."
    (cd .. && npm install && npm run build)
fi

docker compose up --build -d
docker compose ps

# Populate the database with sample data if it is empty
LAW_COUNT=$(docker compose exec -T db \
    mysql -u statedecoded -pstatedecoded statedecoded -sN \
    -e "SELECT COUNT(*) FROM laws;" 2>/dev/null || echo 0)
if [ "${LAW_COUNT:-0}" = "0" ]; then
    echo "Database is empty — importing sample data..."
    docker compose exec -T app php statedecoded import -d=/var/www/html/deploy/import-data/
    echo "Import complete."
fi

echo ""
echo "Site: http://localhost:8080/"
echo "Admin: http://localhost:8080/admin/  (user: admin  pass: admin)"
