#!/bin/bash

cd /var/www/

# Move over the settings file.
cp deploy/config-docker.inc.php htdocs/includes/config.inc.php

# Move over the custom functions file.
cp deploy/class.Virginia.inc.php htdocs/includes/class.Virginia.inc.php
