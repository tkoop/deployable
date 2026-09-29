<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Sanctum 4 writes an expires_at column on every token it creates, but the
     * personal_access_tokens migration this app has carried since 2019 predates
     * it. Minting a token fails without this.
     *
     * Nullable, so tokens created before this migration keep working forever,
     * matching how they behaved until now.
     *
     * @return void
     */
    public function up() {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
        });
    }

    /**
     * @return void
     */
    public function down() {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
