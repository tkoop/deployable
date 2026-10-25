# Managed by Deployable for {{ $site->domain }}.
# Saving the site in Deployable rewrites this file, so edit it there instead.

<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName {{ $site->domain }}
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
    SSLEngine on

    ErrorLog ${APACHE_LOG_DIR}/{{ $site->domain }}_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/{{ $site->domain }}_ssl_access.log combined

    SSLCertificateFile {{ config('deployable.letsencrypt_dir') }}/live/{{ $site->domain }}/fullchain.pem
    SSLCertificateKeyFile {{ config('deployable.letsencrypt_dir') }}/live/{{ $site->domain }}/privkey.pem
@if (is_file($optionsSsl))
    Include {{ $optionsSsl }}
@endif
</VirtualHost>
</IfModule>
