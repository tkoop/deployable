<?php

namespace App\Models;

use App\Helpers\DeploymentManager;
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
}
