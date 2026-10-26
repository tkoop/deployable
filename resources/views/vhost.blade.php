# Managed by Deployable for {{ $site->domain }}.
# Saving the site in Deployable rewrites this file, so edit it there instead.
#
# {{ $site->type === 'php' ? "A .htaccess in " . $site->document_root . " applies to every request." : "This is a static site, so .htaccess files here are ignored." }}

<VirtualHost *:80>
    ServerName {{ $site->domain }}
@if (count($site->allHosts()) > 1)
    ServerAlias {{ implode(' ', array_slice($site->allHosts(), 1)) }}
@endif
    DocumentRoot "{{ $site->document_root }}"

    <Directory "{{ $site->document_root }}">
        Options {{ $site->type === 'php' ? 'FollowSymLinks' : '-Indexes +FollowSymLinks' }}
        AllowOverride {{ $site->type === 'php' ? 'All' : 'None' }}
        Require all granted
    </Directory>

@if ($socket !== null)
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:{{ $socket }}|fcgi://localhost"
    </FilesMatch>

@endif
    ErrorLog ${APACHE_LOG_DIR}/{{ $site->domain }}_error.log
    CustomLog ${APACHE_LOG_DIR}/{{ $site->domain }}_access.log combined
@if ($sslActive)

    # The certificate is already in place by the time this redirect appears,
    # so it can never point at an https vhost that isn't there.
    RewriteEngine on
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
@endif
</VirtualHost>
