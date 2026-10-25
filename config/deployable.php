<?php

/*
|--------------------------------------------------------------------------
| Deployable's Apache integration
|--------------------------------------------------------------------------
|
| Every path here comes from one place so the preflight checks, the vhost
| writer and the tests all agree about where things live. Point them at a
| fixture directory in a test and nothing touches the real server.
|
| The values are the defaults for a Debian/Ubuntu Apache install, which is
| what server/install.sh sets up.
|
*/

return [

    // Where the web app drops vhost files. www-data owns it; deployable-vhost
    // validates whatever lands there and reloads Apache. It lives under the
    // app's own storage directory because php-fpm is sandboxed and cannot
    // write to /etc or /var/lib - only to paths inside the application.
    "vhost_dir" => env("DEPLOYABLE_VHOST_DIR", "/var/www/deployable.timkoop.com/storage/app/apache/vhosts"),

    // What deployable-vhost wrote the last time it ran. Root writes this;
    // the app only reads it, so /var/lib is fine.
    "reload_result" => env("DEPLOYABLE_RELOAD_RESULT", "/var/lib/deployable/last-reload.json"),
    "configtest_detail" => env("DEPLOYABLE_CONFIGTEST_DETAIL", "/var/lib/deployable/last-configtest.log"),

    "apache2_conf" => env("DEPLOYABLE_APACHE2_CONF", "/etc/apache2/apache2.conf"),
    "sites_available" => env("DEPLOYABLE_SITES_AVAILABLE", "/etc/apache2/sites-available"),
    "sites_enabled" => env("DEPLOYABLE_SITES_ENABLED", "/etc/apache2/sites-enabled"),
    "mods_enabled" => env("DEPLOYABLE_MODS_ENABLED", "/etc/apache2/mods-enabled"),
    "apache2ctl" => env("DEPLOYABLE_APACHE2CTL", "/usr/sbin/apache2ctl"),

    "php_dir" => env("DEPLOYABLE_PHP_DIR", "/etc/php"),
    "php_socket_dir" => env("DEPLOYABLE_PHP_SOCKET_DIR", "/run/php"),

    // The app writes a *.req, deployable-ssl answers with a *.json. Both
    // directories live under the app's storage for the same sandbox reason.
    "ssl_request_dir" => env("DEPLOYABLE_SSL_REQUEST_DIR", "/var/www/deployable.timkoop.com/storage/app/apache/ssl-requests"),
    "ssl_result_dir" => env("DEPLOYABLE_SSL_RESULT_DIR", "/var/www/deployable.timkoop.com/storage/app/apache/ssl-results"),

    "certbot" => env("DEPLOYABLE_CERTBOT", "/snap/bin/certbot"),
    "letsencrypt_dir" => env("DEPLOYABLE_LETSENCRYPT_DIR", "/etc/letsencrypt"),

    // Seconds to wait for a certificate before telling the user it's pending.
    // php-fpm's default max_execution_time is 30, so stay well under it.
    "ssl_wait_seconds" => (int) env("DEPLOYABLE_SSL_WAIT_SECONDS", 15),

    // Where server/install.sh lives on this server, for fix-it instructions.
    "install_script" => env(
        "DEPLOYABLE_INSTALL_SCRIPT",
        "/var/www/deployable.timkoop.com/server/install.sh"
    ),

    // systemd queries run through this, so tests can stub it with /bin/true
    // or /bin/false instead of having an init system.
    "systemctl" => env("DEPLOYABLE_SYSTEMCTL", "/usr/bin/systemctl"),

    "units" => [
        "vhost" => "deployable-vhost.path",
        "ssl" => "deployable-ssl.path",
    ],
];
