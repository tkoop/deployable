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

	/**
	 * The script that gets run for a deployment.
	 *
	 * The exit status is recorded by a trap on EXIT rather than by reading $?
	 * after the hook script inline. A hook script is arbitrary bash, so it may
	 * call exit itself, or turn on `set -e` and give up partway; either way the
	 * trap still fires and the deployment still gets a verdict. Reading $?
	 * directly would skip the recording whenever the script ended early, and
	 * that is exactly the case worth knowing about.
	 */
	private function makeScript() {
		$path = base_path();

		$lines = [
			"#!/bin/bash",
			"export NO_COLOR=1",
			"cd ..",
			"",
			"record_deploy_end() {",
			"\t# \$? first, on its own line: nothing may run in between or it is",
			"\t# no longer the script's exit status.",
			"\tstatus=\$?",
			"\techo \"Deployment finished at `date`\"",
			"\tphp {$path}/artisan deploy:end {$this->deployment->id} \$status",
			"}",
			"trap record_deploy_end EXIT",
			"",
			"php {$path}/artisan deploy:start {$this->deployment->id}",
			"echo \"Deployment started at `date`\"",
			"",
			$this->deployment->hook->script,
		];

		return implode("\n", $lines) . "\n";
	}

	public function runScript() {
		$path = $this->path();
		mkdir($path, recursive: true);

		file_put_contents("{$path}/script.sh", $this->makeScript());
		exec("chmod u+x {$path}/script.sh");

		$command = "{$path}/script.sh > {$path}/output.txt 2>&1 &";
		Log::debug($command);
		exec($command);
	}

	/**
	 * The output as it should be shown in a browser.  Deploy scripts emit ANSI
	 * escape codes; those colour codes become styled text, everything else is
	 * dropped.
	 */
	public function getOutputHtml() {
		if (file_exists($this->path() . "/output.txt")) {
			return AnsiToHtml::convert(file_get_contents($this->path() . "/output.txt"));
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
