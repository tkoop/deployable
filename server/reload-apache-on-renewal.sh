#!/bin/sh
#
# Installed by server/install.sh as
# /etc/letsencrypt/renewal-hooks/deploy/00-reload-apache.sh
#
# certbot runs this as root after every successful renewal. Without it the new
# certificate sits on disk while Apache keeps serving the old one, and the site
# breaks when the old one expires.

systemctl reload apache2
