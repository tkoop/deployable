<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('sites', function (Blueprint $table) {
            // Additional host names this site answers on, beyond its primary
            // domain. Stored as a JSON array of lowercase domain strings.
            $table->json('aliases')->nullable()->after('domain');
        });
    }

    public function down() {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};