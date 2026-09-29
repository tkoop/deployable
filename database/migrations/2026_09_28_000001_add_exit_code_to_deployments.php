<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Record how a deploy script actually exited.
     *
     * Nothing was captured before, so a deployment could only ever be marked
     * "done" and there was no way to tell a successful deploy from a script
     * that had fallen over. Nullable, because deployments that haven't
     * finished (and rows that predate this) have no exit status to report.
     *
     * @return void
     */
    public function up() {
        Schema::table('deployments', function (Blueprint $table) {
            $table->integer('exit_code')->nullable();
        });
    }

    /**
     * @return void
     */
    public function down() {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn('exit_code');
        });
    }
};
