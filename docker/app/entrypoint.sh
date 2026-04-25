#!/bin/bash
set -e

CONFIG_SRC="/var/www/html/docker/config/config.inc.docker.php"
CONFIG_DST="/var/www/html/includes/config.inc.php"
STATE_SRC="/var/www/html/includes/class.State-sample.inc.php"
STATE_DST="/var/www/html/includes/class.State.inc.php"
CONFIG_TEST_SRC="/var/www/html/docker/config/config-test.inc.docker.php"
CONFIG_TEST_DST="/var/www/html/includes/config-test.inc.php"

# Drop in config.inc.php from the Docker template unless a real one is already present
if [ ! -f "$CONFIG_DST" ]; then
    cp "$CONFIG_SRC" "$CONFIG_DST"
    echo "[entrypoint] Installed config.inc.php from Docker template"
fi

# Drop in class.State.inc.php from the sample unless a real one is already present
if [ ! -f "$STATE_DST" ]; then
    cp "$STATE_SRC" "$STATE_DST"
    echo "[entrypoint] Installed class.State.inc.php from sample"
fi

# Drop in config-test.inc.php for the test suite
if [ ! -f "$CONFIG_TEST_DST" ]; then
    cp "$CONFIG_TEST_SRC" "$CONFIG_TEST_DST"
    echo "[entrypoint] Installed config-test.inc.php from Docker template"
fi

# Wait for MySQL to accept connections before starting Apache
DB_HOST="${MYSQL_HOST:-db}"
DB_PORT="${MYSQL_PORT:-3306}"
echo "[entrypoint] Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" -u "${MYSQL_USER:-statedecoded}" -p"${MYSQL_PASSWORD:-statedecoded}" --skip-ssl --silent 2>/dev/null; do
    sleep 1
done
echo "[entrypoint] MySQL is ready"

exec "$@"
