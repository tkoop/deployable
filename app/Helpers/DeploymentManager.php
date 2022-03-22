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

	private function path() {
		return storage_path("deployments/" . $this->deployment->id);
	}

	private function makeScript() {
		return "#!/bin/bash\n" .
			"cd ..\n" .
			"echo \"Deployment started at `date`\"\n" .
			$this->deployment->hook->script . "\n" .
			"echo \"Deployment finished at `date`\"";
	}

	public function runScript() {
		$path = $this->path();
		mkdir($path, recursive: true);

		file_put_contents("{$path}/script.sh", $this->makeScript());
		exec("chmod u+x {$path}/script.sh");

		$command = "{$path}/script.sh > {$path}/output.txt &";
		Log::debug($command);
		exec($command);
	}

	public function getOutput() {
		if (file_exists($this->path() . "/output.txt")) {
			return file_get_contents($this->path() . "/output.txt");
		}
		return "No output was created.";
	}

	public static function start(Hook $hook): Deployment {
		/** @var Deployment $deployment  */
		$deployment = Deployment::create(["hook_id" => $hook->id]);

		$deployment->manager()->runScript();

		return $deployment;
	}
}
