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

# Populate the test database with sample data if it is empty.
# PHPUnit connects to statedecoded_test (via config-test.inc.docker.php).
TEST_LAW_COUNT=$(docker compose -f deploy/docker-compose.yml exec -T db \
    mysql -u statedecoded -pstatedecoded statedecoded_test -sN \
    -e "SELECT COUNT(*) FROM laws;" 2>/dev/null || echo 0)
if [ "${TEST_LAW_COUNT:-0}" = "0" ]; then
    echo "Test database is empty — migrating and importing sample data..."
    docker compose -f deploy/docker-compose.yml exec -T app \
        php statedecoded \
            -c=deploy/docker/config/config-test.inc.docker.php \
            migrate
    docker compose -f deploy/docker-compose.yml exec -T app \
        php statedecoded \
            -c=deploy/docker/config/config-test.inc.docker.php \
            import \
            -d=/var/www/html/deploy/import-data/
    echo "Import complete."
fi

# Fetch a verified API key from the live DB for smoke tests.
SMOKE_API_KEY=$(docker compose -f deploy/docker-compose.yml exec -T db \
    mysql -u statedecoded -pstatedecoded statedecoded -sN \
    -e "SELECT api_key FROM api_keys WHERE verified='y' LIMIT 1;" 2>/dev/null \
    | tr -d '\r' || echo '')

phpunit_cmd() {
    docker compose -f deploy/docker-compose.yml exec \
        -e SMOKE_BASE_URL=http://localhost \
        -e SMOKE_API_KEY="$SMOKE_API_KEY" \
        app vendor/bin/phpunit -c includes/test/phpunit.xml "$@"
}

case "$TOOL" in
    phpstan)
        docker compose -f deploy/docker-compose.yml exec app vendor/bin/phpstan analyse "$@"
        ;;
    phpunit)
        phpunit_cmd "$@"
        ;;
    all)
        FAILED=0
        docker compose -f deploy/docker-compose.yml exec app vendor/bin/phpstan analyse || FAILED=1
        phpunit_cmd || FAILED=1
        exit $FAILED
        ;;
    *)
        echo "Usage: $(basename "$0") [phpstan|phpunit] [args...]" >&2
        exit 1
        ;;
esac
