<?php

namespace App\View\Components;

use App\Models\Hook;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;

class HookLayout extends Component {
	public $hook;

	function __construct(Hook $hook) {
		Log::debug("hook layout constructed ");
		Log::debug(print_r($hook, true));
		Log::debug($hook->id);

		$this->hook = $hook;
	}

	/**
	 * Get the view / contents that represents the component.
	 *
	 * @return \Illuminate\View\View
	 */
	public function render() {
		Log::debug("hook layout render");
		Log::debug($this->hook->id);
		return view('layouts.hook');
	}
}
