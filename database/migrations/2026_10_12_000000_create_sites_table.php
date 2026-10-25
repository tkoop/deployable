<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();

            // A hook has at most one site, and losing the hook loses the site:
            // the site is what that hook's directory is served as.
            $table->foreignId('hook_id')->unique()->constrained()->cascadeOnDelete();

            // Unique in its own right, because two sites claiming one name is
            // a config Apache loads in whichever order it feels like.
            $table->string('domain')->unique();

            $table->string('document_root');

            // php runs files through php-fpm; static serves them as they are.
            $table->string('type')->default('php');

            // Which php-fpm socket the vhost points at, e.g. "8.3". Left blank
            // until a site is saved, so nothing is pinned to a version this
            // particular server may not have.
            $table->string('php_version')->default('');

            $table->boolean('enabled')->default(true);

            // none: no certificate asked for
            // pending: a request is waiting on certbot
            // active: a certificate is on disk and the vhost is written
            // failed: certbot said no; ssl_output says why
            $table->string('ssl_state')->default('none');
            $table->string('ssl_email')->nullable();
            $table->string('ssl_nonce')->nullable();
            $table->text('ssl_output')->nullable();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('sites');
    }
};
