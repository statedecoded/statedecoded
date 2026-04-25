#!/bin/bash

cd "$(dirname "$0")"

TOOL="${1:-all}"
[ "$TOOL" != "all" ] && shift

# Start the Docker stack if not already running; shut it down when we exit if we started it
if docker compose ps --status running --services 2>/dev/null | grep -q "^app$"; then
    STARTED=false
else
    STARTED=true
    ./docker-run.sh
fi

cleanup() {
    if [ "$STARTED" = true ]; then
        ./docker-stop.sh
    fi
}
trap cleanup EXIT

case "$TOOL" in
    phpstan)
        docker compose exec app vendor/bin/phpstan analyse "$@"
        ;;
    phpunit)
        docker compose exec app vendor/bin/phpunit -c includes/test/phpunit.xml "$@"
        ;;
    all)
        FAILED=0
        docker compose exec app vendor/bin/phpstan analyse           || FAILED=1
        docker compose exec app vendor/bin/phpunit -c includes/test/phpunit.xml || FAILED=1
        exit $FAILED
        ;;
    *)
        echo "Usage: $(basename "$0") [phpstan|phpunit] [args...]" >&2
        exit 1
        ;;
esac
