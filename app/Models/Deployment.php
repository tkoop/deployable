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

	public function hook(): BelongsTo {
		return $this->belongsTo(Hook::class);
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
