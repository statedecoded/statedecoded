#!/bin/bash

# Make localhost the name of the host
grep "ServerName localhost" /etc/apache2/apache2.conf
if [ $? -ne 0 ]; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

# Make /var/www/htdocs the webroot
grep "html" /etc/apache2/apache2.conf
if [ $? -ne 0 ]; then
    sed -i 's/html/htdocs/g' /etc/apache2/apache2.conf
fi
