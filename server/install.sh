#!/bin/bash
#
# Install Deployable's Apache integration.  Run as root, once per server:
#
#     sudo bash server/install.sh
#
# Everything here is idempotent - run it again after a git pull and it only
# changes what needs changing.
#
# What gets installed:
#
#   /etc/apache2/deployable/          where the web app drops vhost files
#   /usr/local/sbin/deployable-vhost  validates them and reloads Apache
#   /usr/local/sbin/deployable-ssl    runs certbot for requested domains
#   deployable-vhost.{path,service}   systemd trigger for the first one
#   deployable-ssl.{path,service}     systemd trigger for the second one
#   /etc/deployable/ssl-{requests,results}/   the app's inbox and the reply
#   /etc/letsencrypt/renewal-hooks/...        reload Apache after renewals
#
# The web app itself never runs a privileged command: it writes files, and
# these units notice.

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INCLUDE='IncludeOptional /var/www/deployable.timkoop.com/storage/app/apache/vhosts/*.conf'

echo "Directories"
install -d -m 0755 -o www-data -g www-data /var/www/deployable.timkoop.com/storage/app/apache/vhosts
install -d -m 0755 -o www-data -g www-data /var/www/deployable.timkoop.com/storage/app/apache/ssl-requests
install -d -m 0755 -o root    -g root    /var/www/deployable.timkoop.com/storage/app/apache/ssl-results
install -d -m 0755 -o root    -g root    /var/lib/deployable
echo "  vhosts + ssl-requests + ssl-results under storage/app/apache, /var/lib/deployable"

echo "Helper scripts"
install -m 0755 "$HERE/deployable-vhost" /usr/local/sbin/deployable-vhost
install -m 0755 "$HERE/deployable-ssl"    /usr/local/sbin/deployable-ssl
echo "  /usr/local/sbin/deployable-{vhost,ssl}"

echo "apache2.conf"
if [ ! -f /etc/apache2/apache2.conf.pre-deployable ]; then
    cp -a /etc/apache2/apache2.conf /etc/apache2/apache2.conf.pre-deployable
    echo "  backed up to apache2.conf.pre-deployable"
fi

python3 - "$INCLUDE" <<'PY'
import sys

path = "/etc/apache2/apache2.conf"
include = sys.argv[1]
marker = "IncludeOptional sites-enabled/*.conf"
backup = "/etc/apache2/apache2.conf.pre-deployable"

text = open(path).read()

if include in text:
    print(f"  {include}  (already there)")
else:
    block = f"# Drop-in virtual hosts managed by Deployable.\n{include}\n"
    text = text.replace(marker, block + marker, 1) if marker in text else text.rstrip("\n") + "\n" + block
    open(path, "w").write(text)
    print(f"  added {include}")
PY

echo "systemd units"
install -m 0644 "$HERE/deployable-vhost.path"    /etc/systemd/system/
install -m 0644 "$HERE/deployable-vhost.service" /etc/systemd/system/
install -m 0644 "$HERE/deployable-ssl.path"      /etc/systemd/system/
install -m 0644 "$HERE/deployable-ssl.service"   /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now deployable-vhost.path deployable-ssl.path
systemctl is-enabled deployable-vhost.path deployable-ssl.path | sed 's/^/  /'

echo "Certificate renewals"
install -d -m 0755 /etc/letsencrypt/renewal-hooks/deploy
install -m 0755 "$HERE/reload-apache-on-renewal.sh" \
    /etc/letsencrypt/renewal-hooks/deploy/00-reload-apache.sh
echo "  /etc/letsencrypt/renewal-hooks/deploy/00-reload-apache.sh"

echo "Checking Apache's configuration"
if apache2ctl configtest; then
    systemctl reload apache2
    echo "  Apache reloaded."
else
    echo "apache2ctl configtest failed - Apache was NOT reloaded." >&2
    echo "Fix the error above, then re-run this script." >&2
    exit 1
fi

echo "First run of the validator"
/usr/local/sbin/deployable-vhost || true
cat /var/lib/deployable/last-reload.json | sed 's/^/  /'

echo
echo "Done. In Deployable, the hook's Site page will now report every check as passing."
