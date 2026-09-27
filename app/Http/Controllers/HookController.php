<?php

namespace App\Http\Controllers;

use App\Helpers\ConfigCache;
use App\Models\Hook;
use Illuminate\Http\Request;

class HookController extends Controller {

	public function run($slug) {
		$hook = Hook::where("slug", $slug)->first();
		if ($hook == null) {
			abort(404);
		}

		$deployment = $hook->start();
		return "Success";
	}

	public function viewNew() {
		$slug = rand(0, 99999999) . rand(0, 99999999);
		$baseURL = url('run');

		return view('newHook', ["slug" => $slug, "baseURL" => $baseURL]);
	}

	public function doNew() {
		request()->validate([
			"name" => "required",
			"slug" => "required",
		] + $this->directoryRules(), $this->messages());

		$attributes = [
			"name" => request("name"),
			"slug" => request("slug"),
		];

		if (request()->filled("directory")) {
			$attributes["directory"] = request("directory");
		}

		$hook = Hook::create($attributes);

		return redirect('/hook/' . $hook->id . '/view');
	}

	public function deploy(Hook $hook) {
		$deployment = $hook->start();
		return redirect('/deployment/' . $deployment->id);
	}

	public function view(Hook $hook) {
		return redirect("/hook/" . $hook->id . "/edit");
	}

	public function viewEdit(Hook $hook) {
		return view('hookEdit', ["hook" => $hook, "baseURL" => url('run')]);
	}

	public function deployments(Hook $hook) {
		$deployments = $hook->deployments()->orderBy("created_at", "desc")->get();
		return view('deployments', ["hook" => $hook, "deployments" => $deployments]);
	}

	public function viewEnv(Hook $hook) {
		// There's no .env to edit until the project directory is on disk.
		if (!$hook->envFile()->directoryExists()) {
			abort(404);
		}

		return view('hookEnv', ["hook" => $hook, "envFile" => $hook->envFile()]);
	}

	public function doEnv(Hook $hook) {
		if (!$hook->envFile()->directoryExists()) {
			abort(404);
		}

		// An emptied textarea arrives as null, courtesy of ConvertEmptyStringsToNull.
		$contents = (string) request("env");

		if (!$hook->envFile()->set($contents)) {
			return back()->withErrors(["env" => "Couldn't write the .env file.  Is the directory writable?"]);
		}

		$redirect = redirect('/hook/' . $hook->id . '/env')->withStatus("Environment file was saved.");

		if (request()->has("refreshConfig")) {
			$result = ConfigCache::forHook($hook)->refresh();

			$redirect->with("configOutput", $result["output"]);

			// The .env itself is safely on disk either way, so say so rather
			// than implying the whole save failed.
			if (!$result["success"]) {
				$redirect->withErrors(["config" => "The .env was saved, but the config cache could not be rebuilt."]);
			}
		}

		return $redirect;
	}

	public function doEdit(Hook $hook) {
		if (request()->has("delete")) {
			$hook->delete();
			return redirect('/')->withStatus("Hook was deleted.");
		}

		request()->validate([
			"name" => "required",
			"slug" => "required",
			"script" => "required",
		] + $this->directoryRules(), $this->messages());

		$hook->name = request("name");
		$hook->slug = request("slug");
		$hook->script = str_replace("\r", "", request("script"));

		if (request()->filled("directory")) {
			$hook->directory = request("directory");
		}

		$hook->save();

		return redirect('/hook/' . $hook->id . '/edit')->withStatus("Hook was saved.");
	}

	/**
	 * The directory is optional, and it isn't required to exist yet.  It does
	 * have to be a full path if it's given, since there's no obvious working
	 * directory to resolve a relative one against.
	 *
	 * Note that nothing here requires the column to exist: a form that predates
	 * it won't send a directory at all, and callers skip the column entirely
	 * when one wasn't sent, so saving still works before the migration runs.
	 */
	private function directoryRules(): array {
		return [
			"directory" => "nullable|starts_with:/",
		];
	}

	private function messages(): array {
		return [
			"directory.starts_with" => "The project directory must be a full path, like /var/www/example.",
		];
	}
}
