<?php

namespace App\Helpers;

use App\Models\Hook;
use Illuminate\Support\Facades\Log;

/**
 * Reads and writes the .env file that belongs to a hook's project directory.
 *
 * The file name is never taken from user input, so the only thing a hook can
 * ever reach is a ".env" inside the directory configured for that hook.
 */
class EnvFile {
	private $hook;

	public function __construct(Hook $hook) {
		$this->hook = $hook;
	}

	public static function forHook(Hook $hook): EnvFile {
		return new EnvFile($hook);
	}

	/**
	 * The configured project directory, or an empty string if it isn't usable.
	 */
	public function directory(): string {
		$directory = trim((string) $this->hook->directory);

		// A null byte would truncate the path, and a relative path is ambiguous
		// because we have no idea what the working directory would be.
		if (str_contains($directory, "\0")) return "";
		if (!str_starts_with($directory, "/")) return "";

		return $directory;
	}

	public function directoryExists(): bool {
		$directory = $this->directory();

		return $directory !== "" && is_dir($directory);
	}

	public function path(): string {
		$directory = $this->directory();

		return $directory === "" ? "" : rtrim($directory, "/") . "/.env";
	}

	/**
	 * Whether there is already an .env file to read.  The directory existing
	 * doesn't mean the file does, so this is separate from directoryExists().
	 */
	public function exists(): bool {
		return $this->directoryExists() && is_file($this->path());
	}

	/**
	 * @return string the contents of the file, or an empty string if it isn't there yet
	 */
	public function get(): string {
		if (!$this->exists()) return "";

		$contents = file_get_contents($this->path());

		return $contents === false ? "" : $contents;
	}

	/**
	 * The variable names defined in the file, in the order they appear.
	 * Values are deliberately not returned: this app shows them, and .env
	 * files are full of secrets.
	 *
	 * @return array<int, string>
	 */
	public function variableNames(): array {
		$names = [];

		foreach (preg_split('/\R/', $this->get()) as $line) {
			if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches)) {
				$names[] = $matches[1];
			}
		}

		return array_values(array_unique($names));
	}

	/**
	 * Writes the file, creating it if necessary.  The write goes to a
	 * temporary file in the same directory and is then renamed over the top,
	 * so a failure part way through can't leave a truncated .env behind.
	 */
	public function set(string $contents): bool {
		if (!$this->directoryExists()) {
			Log::warning("Refusing to write .env for hook {$this->hook->id}: {$this->hook->directory} is not a directory.");
			return false;
		}

		$path = $this->path();

		$contents = str_replace("\r", "", $contents);
		if ($contents !== "" && !str_ends_with($contents, "\n")) {
			$contents .= "\n";
		}

		// Keep whatever permissions the file already had, and default to
		// owner-only for a new one since it holds secrets.
		$mode = is_file($path) ? (fileperms($path) & 0777) : 0600;

		$temp = $path . "." . uniqid() . ".tmp";

		if (file_put_contents($temp, $contents, LOCK_EX) === false) {
			@unlink($temp);
			return false;
		}

		chmod($temp, $mode);

		if (!rename($temp, $path)) {
			@unlink($temp);
			return false;
		}

		return true;
	}
}
