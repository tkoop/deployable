<?php

namespace App\Helpers;

use App\Models\Site;
use Illuminate\Support\Facades\View;

/**
 * Reads and writes the vhost files for one site.
 *
 * This helper holds no privilege at all. It drops files into the drop-in
 * directory as www-data and lets systemd notice: deployable-vhost validates
 * them, refuses anything it can't trust, and reloads Apache. That's why every
 * write here ends by asking whether Apache has actually picked the change up,
 * rather than assuming the rename was the whole job.
 *
 * Files are written to a dotfile in the same directory and renamed over the
 * target, so a half-written config can never be seen by the validator, and
 * "disabled" is a rename to *.conf.disabled rather than a delete - which keeps
 * a site's settings around while it's turned off.
 */
class Vhost {
	public function __construct(private Site $site) {
	}

	public static function forSite(Site $site): Vhost {
		return new Vhost($site);
	}

	public function dir(): string {
		return rtrim(config("deployable.vhost_dir"), "/");
	}

	public function path(): string {
		return $this->dir() . "/" . $this->site->domain . ".conf";
	}

	public function disabledPath(): string {
		return $this->path() . ".disabled";
	}

	public function sslPath(): string {
		return $this->dir() . "/" . $this->site->domain . "-le-ssl.conf";
	}

	public function sslDisabledPath(): string {
		return $this->sslPath() . ".disabled";
	}

	/**
	 * Where Apache writes this site's error log.  The vhost template points
	 * ErrorLog at ${APACHE_LOG_DIR}/<domain>_error.log, so mirror that here.
	 */
	public function errorLogPath(): string {
		$dir = $this->logDir();

		return rtrim($dir, "/") . "/" . $this->site->domain . "_error.log";
	}

	public function sslErrorLogPath(): string {
		$dir = $this->logDir();

		return rtrim($dir, "/") . "/" . $this->site->domain . "_ssl_error.log";
	}

	public function phpErrorLogPath(): string {
		return $this->errorLogPath();
	}

	public function logPath(): string {
		return $this->errorLogPath();
	}

	private function logDir(): string {
		foreach (["apache_log_dir", "error_log_dir", "log_dir"] as $key) {
			$value = config("deployable.{$key}");

			if (is_string($value) && $value !== "") {
				return $value;
			}
		}

		return "/var/log/apache2";
	}

	public function exists(): bool {
		return is_file($this->path()) || is_file($this->disabledPath());
	}

	/**
	 * Every file this site could be using right now.
	 *
	 * @return array<int, string>
	 */
	public function files(): array {
		return array_values(array_filter([
			$this->path(),
			$this->disabledPath(),
			$this->sslPath(),
			$this->sslDisabledPath(),
		], "is_file"));
	}

	/**
	 * Write the config for the site as it's currently set up.
	 *
	 * @return array{success: bool, output: string}
	 */
	public function write(): array {
		if (trim($this->site->domain) === "") {
			return ["success" => false, "output" => "There's no domain to name the site config after."];
		}

		if (!is_dir($this->dir())) {
			return [
				"success" => false,
				"output" => "There's no {$this->dir()} directory. Run " .
					config("deployable.install_script") . " on the server first.",
			];
		}

		// With SSL on, the https vhost has to exist before anything starts
		// redirecting to it. With SSL off, the redirect has to go before the
		// https vhost does. Both orders keep https never pointed at a gap.
		if ($this->site->sslActive()) {
			if (!$this->put($this->sslTarget(), $this->renderSsl())) {
				return ["success" => false, "output" => "Couldn't write {$this->sslPath()}."];
			}
		}

		if (!$this->put($this->target(), $this->renderHttp())) {
			return ["success" => false, "output" => "Couldn't write {$this->target()}."];
		}

		if (!$this->site->sslActive()) {
			$this->removeSsl();
		}

		return $this->applied("Wrote the site config for {$this->site->domain}.");
	}

	/**
	 * @return array{success: bool, output: string}
	 */
	public function enable(): array {
		$moved = $this->rename([
			$this->sslDisabledPath() => $this->sslPath(),
			$this->disabledPath() => $this->path(),
		]);

		if ($moved === 0) {
			return [
				"success" => false,
				"output" => "There's no site config to enable. Save the site first and one will be written.",
			];
		}

		return $this->applied("Enabled {$this->site->domain}.");
	}

	/**
	 * @return array{success: bool, output: string}
	 */
	public function disable(): array {
		$moved = $this->rename([
			$this->path() => $this->disabledPath(),
			$this->sslPath() => $this->sslDisabledPath(),
		]);

		if ($moved === 0) {
			return ["success" => false, "output" => "{$this->site->domain} isn't enabled, so there's nothing to disable."];
		}

		return $this->applied("Disabled {$this->site->domain}.");
	}

	/**
	 * Take the site's files out of the directory. Nothing else on the server
	 * is touched: a document root stays exactly where it is.
	 *
	 * @return array{success: bool, output: string}
	 */
	public function delete(): array {
		foreach ([$this->path(), $this->disabledPath(), $this->sslPath(), $this->sslDisabledPath()] as $file) {
			@unlink($file);
		}

		return $this->applied("Deleted the site config for {$this->site->domain}.");
	}

	/**
	 * Whether Apache has loaded what's currently on disk, and what the
	 * validator said the last time it ran.
	 */
	public function status(): array {
		$result = Preflight::reloadResult() ?? [];
		$reloadedAt = isset($result["reloaded_at"]) ? strtotime($result["reloaded_at"]) : null;

		$newest = $this->newestMtime();
		$pending = $newest === null ? false : ($reloadedAt === null || $newest > $reloadedAt);

		return [
			"result" => $result,
			"pending" => $pending,
			"rejected" => $this->rejected(),
			"validator_has_run" => Preflight::reloadResult() !== null,
		];
	}

	/**
	 * The rejections that belong to this site, with Apache's own words.
	 *
	 * @return array<int, array{file: string, reason: string}>
	 */
	public function rejected(): array {
		$domain = $this->site->domain;

		return array_values(array_filter(
			Preflight::rejectedFiles(),
			fn ($entry) => str_starts_with($entry["file"], $domain . "."),
		));
	}

	/**
	 * Give the path unit a few seconds to notice, then report what happened.
	 *
	 * The reload is asynchronous on purpose - that's the whole design - so
	 * "the rename worked" and "Apache is serving it" are different facts, and
	 * only the second one is worth telling the user.
	 *
	 * @return array{success: bool, output: string}
	 */
	private function applied(string $message, int $seconds = 3): array {
		$status = $this->wait($seconds);

		if ($rejected = $this->rejected()) {
			$reasons = array_map(
				fn ($entry) => "{$entry['file']}: {$entry['reason']}",
				$rejected,
			);

			$detail = trim((string) @file_get_contents(config("deployable.configtest_detail")));

			return [
				"success" => false,
				"output" => "Apache refused the site config:\n" . implode("\n", $reasons) .
					($detail !== "" ? "\n\n{$detail}" : "") .
					"\n\nThe rest of the server is unaffected - the file was moved aside. " .
					"Fix it and save the site again.",
			];
		}

		if (!$status["applied"]) {
			return [
				"success" => true,
				"output" => $message . "\n\n" . $status["reason"] .
					"\n\nRun the checklist above to see what's missing.",
			];
		}

		return ["success" => true, "output" => $message];
	}

	/**
	 * @return array{applied: bool, reason: string, result: array<string, mixed>}
	 */
	private function wait(int $seconds = 3): array {
		$deadline = microtime(true) + $seconds;
		$last = [];

		do {
			$result = Preflight::reloadResult() ?? [];
			$reloadedAt = isset($result["reloaded_at"]) ? strtotime($result["reloaded_at"]) : null;
			$newest = $this->newestMtime();

			if ($newest !== null && $reloadedAt !== null && $reloadedAt >= $newest) {
				return ["applied" => true, "reason" => $result["message"] ?? "", "result" => $result];
			}

			$last = $result;
			usleep(250000);
		} while (microtime(true) < $deadline);

		return ["applied" => false, "reason" => $this->whyNot($last), "result" => $last];
	}

	private function whyNot(array $result): string {
		if (!isset($result["reloaded_at"])) {
			return "Nothing in {$this->dir()} has ever been loaded into Apache.";
		}

		if (isset($result["config_ok"]) && !$result["config_ok"]) {
			return "The validator's last run found a broken config: " . ($result["message"] ?? "");
		}

		return "The reload service hasn't run yet.";
	}

	private function newestMtime(): int|null {
		$times = array_map("filemtime", array_filter($this->files(), fn ($file) => is_file($file) && !str_contains($file, ".disabled")));

		return $times ? max($times) : null;
	}

	private function target(): string {
		return $this->site->enabled ? $this->path() : $this->disabledPath();
	}

	private function sslTarget(): string {
		return $this->site->enabled ? $this->sslPath() : $this->sslDisabledPath();
	}

	private function removeSsl(): void {
		@unlink($this->sslPath());
		@unlink($this->sslDisabledPath());
	}

	/**
	 * @param array<string, string> $pairs
	 * @return int how many renames happened
	 */
	private function rename(array $pairs): int {
		$moved = 0;

		foreach ($pairs as $from => $to) {
			if (is_file($from) && !is_file($to)) {
				if (@rename($from, $to)) {
					$moved++;
				}
			}
		}

		return $moved;
	}

	private function renderHttp(): string {
		return View::make("vhost", [
			"site" => $this->site,
			"socket" => $this->socket(),
			"sslActive" => $this->site->sslActive(),
			"optionsSsl" => $this->optionsSsl(),
		])->render();
	}

	private function renderSsl(): string {
		return View::make("vhostSsl", [
			"site" => $this->site,
			"socket" => $this->socket(),
			"optionsSsl" => $this->optionsSsl(),
		])->render();
	}

	private function socket(): string|null {
		return $this->site->type === "php"
			? Preflight::phpSocket($this->site->php_version)
			: null;
	}

	private function optionsSsl(): string {
		return config("deployable.letsencrypt_dir") . "/options-ssl-apache.conf";
	}

	private function put(string $target, string $contents): bool {
		$contents = str_replace("\r", "", $contents);

		if (!str_ends_with($contents, "\n")) {
			$contents .= "\n";
		}

		// A dotfile, so the validator's *.conf glob can't see a half-written
		// config even for an instant.
		$temp = dirname($target) . "/.tmp-" . bin2hex(random_bytes(6));

		if (@file_put_contents($temp, $contents) === false) {
			return false;
		}

		@chmod($temp, 0644);

		if (!@rename($temp, $target)) {
			@unlink($temp);
			return false;
		}

		return true;
	}
}
