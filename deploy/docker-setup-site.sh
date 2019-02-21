#!/bin/bash

cd /var/www/

# What this image calls html, we call htdocs
if [ -d html ]; then
    rmdir html
    ln -s htdocs html
elif [ ! -e html]; then
    ln -s htdocs html
fi

# Move over the settings file.
cp deploy/config-docker.inc.php includes/config.inc.php

# Move over the custom functions file.
cp deploy/class.Virginia.inc.php includes/class.Virginia.inc.php

# Move over the sample import data.
cp deploy/import-data.zip htdocs/admin/
cd htdocs/admin/ && unzip -u import-data.zip
rm import-data.zip

cd /var/www/
./statedecoded import
