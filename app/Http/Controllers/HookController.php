<?php

namespace App\Http\Controllers;

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

		$hook = Hook::create([
			"name" => request("name"),
			"slug" => request("slug"),
			"directory" => request("directory"),
		]);

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

		return redirect('/hook/' . $hook->id . '/env')->withStatus("Environment file was saved.");
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
		$hook->directory = request("directory");
		$hook->save();

		return redirect('/hook/' . $hook->id . '/edit')->withStatus("Hook was saved.");
	}

	/**
	 * The directory doesn't have to exist yet, but it does have to be a full
	 * path, since there's no obvious working directory to resolve a relative
	 * one against when the deploy script runs.
	 */
	private function directoryRules(): array {
		return [
			"directory" => "required|starts_with:/",
		];
	}

	private function messages(): array {
		return [
			"directory.required" => "A project directory is required.",
			"directory.starts_with" => "The project directory must be a full path, like /var/www/example.",
		];
	}
}
