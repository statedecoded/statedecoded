#!/bin/bash

cd /var/www/

# What this image calls html, we call htdocs
if [ -f html ]; then
    rmdir html
    ln -s htdocs html
else
    ln -s htdocs html
fi

# Move over the settings file.
cp deploy/config-docker.inc.php includes/config.inc.php

# Move over the custom functions file.
cp deploy/class.Virginia.inc.php includes/class.Virginia.inc.php
