<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeployEnded extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * Called from the EXIT trap in the generated deploy script, which passes
	 * along the status the hook script exited with.
	 *
	 * @var string
	 */
	protected $signature = 'deploy:ended {deployment_id} {exit_code=0}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Deploy ended';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return int
	 */
	public function handle() {
		$deployment = Deployment::find($this->argument('deployment_id'));

		if ($deployment == null) {
			Log::warning("deploy ended called for missing deployment " . $this->argument('deployment_id'));
			return self::FAILURE;
		}

		$exitCode = (int) $this->argument('exit_code');

		$deployment->update([
			"state" => $exitCode === 0 ? "done" : "failed",
			"ended_at" => now(),
			"exit_code" => $exitCode,
		]);

		Log::debug("deploy ended " . $deployment->id . " with status " . $exitCode);
		return 0;
	}
}
