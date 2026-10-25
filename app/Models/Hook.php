<?php

namespace App\Models;

use App\Helpers\DeploymentManager;
use App\Helpers\EnvFile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hook extends Model {
	use HasFactory;

	protected $guarded = [];

	public function deployments(): HasMany {
		return $this->hasMany(Deployment::class);
	}

	/**
	 * The one Apache site this hook's directory is served as, if any.
	 *
	 * A hook may have none at all - plenty of hooks are just a deploy script -
	 * but it can never have two: the site *is* what this directory is served as.
	 */
	public function site(): HasOne {
		return $this->hasOne(Site::class);
	}

	public function start(): Deployment {
		return DeploymentManager::start($this);
	}

	public function envFile(): EnvFile {
		return EnvFile::forHook($this);
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
