<?php

namespace App\Models;

use App\Helpers\DeploymentManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class Deployment extends Model {
	use HasFactory;

	private $manager = null;

	protected $guarded = [];

	protected $casts = [
		"exit_code" => "integer",
	];

	public function hook(): BelongsTo {
		return $this->belongsTo(Hook::class);
	}

	/**
	 * Is this deployment still going?
	 *
	 * Note this is the recorded state, not a liveness check: the pid column
	 * holds the deploy:started artisan process rather than the script, so a
	 * deployment that died without running its EXIT trap stays in this state
	 * forever.
	 */
	public function isRunning(): bool {
		return in_array($this->state, ["started", "running"], strict: true);
	}

	/**
	 * Did the deploy script actually work?
	 *
	 * Null means "not answerable", which is a real state rather than a
	 * missing value: a deployment that hasn't finished yet, and a row that
	 * predates exit codes being recorded, both have nothing to say. Callers
	 * must not collapse this to false.
	 */
	public function succeeded(): bool|null {
		if ($this->isRunning()) {
			return null;
		}

		if ($this->state === "failed") {
			return false;
		}

		// Done with no recorded status: a deployment from before exit codes
		// were captured. Guessing would be worse than admitting that.
		if ($this->state === "done") {
			return $this->exit_code === null ? null : $this->exit_code === 0;
		}

		return null;
	}

	public function manager(): DeploymentManager {
		if ($this->manager == null) {
			$this->manager = new DeploymentManager($this);
		}
		return $this->manager;
	}

	public function getCreatedAtAttribute($value) {
		return Carbon::parse($value)->timezone('America/Winnipeg');
	}

}
