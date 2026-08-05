#!/bin/bash
set -e

cd "$(dirname "$0")"

if [ ! -f ../.env ]; then
    cp ../.env.example ../.env
    echo "Created .env from .env.example — edit it if needed before re-running."
fi

# Rebuild the front-end assets if any of them are missing. Each path below
# stands for one group of build outputs, so that adding a new kind of asset --
# the self-hosted webfonts, say -- causes an existing checkout to rebuild
# rather than silently starting up without it.
ASSET_ROOT=../htdocs/themes/StateDecoded2013/static
for asset in \
    "$ASSET_ROOT/js/vendor/jquery.min.js" \
    "$ASSET_ROOT/css/application.css" \
    "$ASSET_ROOT/fonts/font-awesome/fontawesome-webfont.woff2" \
    "$ASSET_ROOT/fonts/webfonts/lato-latin-400-normal.woff2" \
    "$ASSET_ROOT/scss/dependencies/_webfonts.scss"
do
    if [ ! -f "$asset" ]; then
        echo "Front-end assets missing ($(basename "$asset")) — running npm install && npm run build..."
        (cd .. && npm install && npm run build)
        break
    fi
done

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
