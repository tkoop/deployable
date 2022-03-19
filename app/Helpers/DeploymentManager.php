<?php

namespace App\Helpers;

use App\Models\Deployment;
use App\Models\Hook;
use Illuminate\Support\Facades\Log;

class DeploymentManager {
	private $deployment;

	public function __construct(Deployment $deployment) {
		$this->deployment = $deployment;
	}

	public function runScript() {
		$path = storage_path("deployments/" . $this->deployment->id);
		Log::debug("runscript " . $path);
		mkdir($path, recursive: true);
	}

	public static function start(Hook $hook): Deployment {
		/** @var Deployment $deployment  */
		$deployment = Deployment::create(["hook_id" => $hook->id]);

		$deployment->manager()->runScript();

		return $deployment;
	}
}
