#!/bin/bash

cd "$(dirname "$0")"

TOOL="${1:-all}"
[ "$TOOL" != "all" ] && shift

# Start the Docker stack if not already running; shut it down when we exit if we started it
if docker compose -f deploy/docker-compose.yml ps --status running --services 2>/dev/null | grep -q "^app$"; then
    STARTED=false
else
    STARTED=true
    ./deploy/docker-run.sh
fi

cleanup() {
    if [ "$STARTED" = true ]; then
        ./deploy/docker-stop.sh
    fi
}
trap cleanup EXIT

# Populate the database with sample data if it is empty
LAW_COUNT=$(docker compose -f deploy/docker-compose.yml exec -T db \
    mysql -u statedecoded -pstatedecoded statedecoded -sN \
    -e "SELECT COUNT(*) FROM laws;" 2>/dev/null || echo 0)
if [ "${LAW_COUNT:-0}" = "0" ]; then
    echo "Database is empty — importing sample data..."
    docker compose -f deploy/docker-compose.yml exec -T app php statedecoded import
    echo "Import complete."
fi

case "$TOOL" in
    phpstan)
        docker compose -f deploy/docker-compose.yml exec app vendor/bin/phpstan analyse "$@"
        ;;
    phpunit)
        docker compose -f deploy/docker-compose.yml exec app vendor/bin/phpunit -c includes/test/phpunit.xml "$@"
        ;;
    all)
        FAILED=0
        docker compose -f deploy/docker-compose.yml exec app vendor/bin/phpstan analyse           || FAILED=1
        docker compose -f deploy/docker-compose.yml exec app vendor/bin/phpunit -c includes/test/phpunit.xml || FAILED=1
        exit $FAILED
        ;;
    *)
        echo "Usage: $(basename "$0") [phpstan|phpunit] [args...]" >&2
        exit 1
        ;;
esac
