<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeployStarted extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'deploy:started {deployment_id}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Deploy started';

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
		$deployment->update(["state" => "running", "pid" => getmypid()]);

		Log::debug("deploy started " . $deployment->id);
		return 0;
	}
}
