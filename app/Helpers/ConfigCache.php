<?php

namespace App\Helpers;

use App\Models\Hook;

/**
 * Rebuilds a project's cached config so that it picks up a .env we just saved.
 *
 * This runs the project's own artisan, not ours, so it has to happen with the
 * project's directory as the working directory.  exec() has no $cwd argument,
 * hence the cd.
 */
class ConfigCache {
	private const COMMAND = "php artisan config:clear && php artisan config:cache";

	private $hook;

	public function __construct(Hook $hook) {
		$this->hook = $hook;
	}

	public static function forHook(Hook $hook): ConfigCache {
		return new ConfigCache($hook);
	}

	/**
	 * @return array{success: bool, output: string}
	 */
	public function refresh(): array {
		$directory = $this->hook->envFile()->directory();

		if ($directory === "" || !is_dir($directory)) {
			return ["success" => false, "output" => "{$directory} is not a directory."];
		}

		if (!is_file($directory . "/artisan")) {
			return ["success" => false, "output" => "There's no artisan file in {$directory}, so there's no config to cache."];
		}

		$command = "cd " . escapeshellarg($directory) . " && { " . self::COMMAND . " ; } 2>&1";

		$output = [];
		$exitCode = 0;

		exec($command, $output, $exitCode);

		return [
			"success" => $exitCode === 0,
			"output" => trim(implode("\n", $output)),
		];
	}
}
