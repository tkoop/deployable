<?php

use App\Models\Deployment;
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
		Schema::table("deployments", function (Blueprint $table) {
			$table->string("state")->default("started")->comment("started, running, done, failed");
			$table->timestamp("ended_at")->nullable();
		});

		Deployment::query()->update(["state" => "done"]);
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		if (Schema::hasColumn("deployments", "ended_at")) {
			Schema::dropColumns("deployments", "ended_at");
		}

		if (Schema::hasColumn("deployments", "state")) {
			Schema::dropColumns("deployments", "state");
		}
	}
};
