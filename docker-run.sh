#!/bin/bash

# Stand it up.
docker-compose build && docker-compose up -d

WEB_ID=$(docker ps |grep sd_web |cut -d " " -f 1)
docker exec "$WEB_ID" /var/www/deploy/docker-setup-site.sh
