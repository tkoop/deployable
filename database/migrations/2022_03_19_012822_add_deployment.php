<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->text('output')->default('');
            $table->integer('pid')->nullable();
            $table->integer('hook_id')->unsigned();
            $table->foreign('hook_id')->references('id')->on('hooks')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('deployments');
    }
};
