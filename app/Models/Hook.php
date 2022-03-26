<?php

namespace App\Models;

use App\Helpers\DeploymentManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hook extends Model {
	use HasFactory;

	protected $guarded = [];

	public function deployments(): HasMany {
		return $this->hasMany(Deployment::class);
	}

	public function start(): Deployment {
		return DeploymentManager::start($this);
	}

	/**
	 * @returns Carbon or null
	 */
	public function lastDeployTime(): Carbon|null {
		$deployment = $this->deployments()->orderBy("created_at", "desc")->limit(1)->first();

		if ($deployment == null) return null;

		return $deployment->created_at;
	}
}
