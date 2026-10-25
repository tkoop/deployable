<?php

namespace App\Helpers;

use App\Models\Hook;
use App\Models\Site;

/**
 * Everything Deployable checks before and after it touches Apache, and the
 * copy-and-paste fix for whatever it finds.
 *
 * The rule here is that a check never says "it's broken" and stops. Each one
 * knows the commands that put it right, in the form you'd paste into an SSH
 * session, plus a way to confirm the fix landed. A directory that isn't there,
 * a unit that never got enabled, a config Apache threw out - all of those are
 * ordinary states with ordinary fixes, not errors to be stared at.
 *
 * Nothing here writes to the system, and nothing here needs root: the checks
 * are filesystem reads plus a few read-only systemctl queries. Where only root
 * can see the truth - like whether a Let's Encrypt key exists, which lives in
 * a 0700 directory - the answer is read back from a file root wrote, rather
 * than by shelling out with sudo.
 */
class Preflight {

	/**
	 * Everything the Apache side of the app depends on.
	 *
	 * @return array<int, Check>
	 */
	public static function server(): array {
		$install = config("deployable.install_script");
		$dropin = config("deployable.vhost_dir");
		$include = "IncludeOptional {$dropin}/*.conf";

		$checks = [];

		// --- Apache itself ---------------------------------------------------

		$apache2ctl = config("deployable.apache2ctl");

		if (!is_file($apache2ctl)) {
			$checks[] = Check::fail(
				"apache_installed",
				"Apache",
				"There's no {$apache2ctl}, so there's no web server on this machine to add sites to.",
				commands: ["sudo apt install apache2"],
				verify: "{$apache2ctl} -v",
			);
		} else {
			$checks[] = Check::pass("apache_installed", "Apache");
		}

		if (self::systemctl("is-active", "--quiet", "apache2") !== 0) {
			$checks[] = Check::fail(
				"apache_running",
				"Apache is running",
				"Apache is installed but not running, so nothing is being served at all.",
				commands: ["sudo systemctl start apache2"],
				verify: "systemctl status apache2",
			);
		} else {
			$checks[] = Check::pass("apache_running", "Apache is running");
		}

		// --- the drop-in directory ------------------------------------------

		if (!is_dir($dropin)) {
			$checks[] = Check::fail(
				"dropin_dir",
				"Site directory",
				"There's no {$dropin} directory, so there's nowhere to put a site config.",
				how: "The install script creates it, hands it to www-data, and wires up the systemd units that watch it. It's safe to run again.",
				commands: ["sudo bash {$install}"],
				verify: "ls -ld {$dropin}",
			);
		} else {
			$checks[] = Check::pass("dropin_dir", "Site directory");
		}

		if (is_dir($dropin)) {
			$owner = self::ownerOf($dropin);

			if (!is_writable($dropin)) {
				$checks[] = Check::fail(
					"dropin_writable",
					"Site directory is writable",
					"Deployable runs as www-data but can't write to {$dropin} (it reports the directory as read-only).",
					how: "The directory is owned by {$owner}. If that isn't www-data, fix the ownership first; otherwise the filesystem or mount is read-only for the web server and needs root attention.",
					commands: ["sudo chown www-data:www-data {$dropin}"],
					verify: "sudo -u www-data touch {$dropin}/.write-test && sudo rm -f {$dropin}/.write-test",
				);
			} else {
				$checks[] = Check::pass("dropin_writable", "Site directory is writable");
			}
		}

		// --- Apache is pointed at that directory ----------------------------

		$apache2conf = config("deployable.apache2_conf");
		$configured = is_file($apache2conf) && str_contains((string) @file_get_contents($apache2conf), $include);

		if (!$configured) {
			$checks[] = Check::fail(
				"apache_include",
				"Apache reads the site directory",
				"Nothing has told Apache to read {$dropin}, so configs written there would sit unnoticed.",
				how: "The install script adds that one line to {$apache2conf} in the right place, and backs the file up first.",
				commands: ["sudo bash {$install}"],
				verify: "grep -nF '{$include}' {$apache2conf}",
			);
		} else {
			$checks[] = Check::pass("apache_include", "Apache reads the site directory");
		}

		// --- the systemd trigger --------------------------------------------

		$checks[] = self::unitCheck(
			config("deployable.units.vhost"),
			"Reload service",
			"There's nothing watching {$dropin}, so a written config would never reach Apache.",
			"sudo systemctl enable --now " . config("deployable.units.vhost"),
			$install,
		);

		// --- the validator's verdict ----------------------------------------

		$result = self::reloadResult();

		if ($result === null) {
			$checks[] = Check::fail(
				"reload_result",
				"Validator has run",
				"Deployable's validator has never run here, so there's no record of Apache ever loading a site config.",
				how: "Run it once by hand. It checks the directory, reloads Apache if anything changed, and writes the result the app reads.",
				commands: ["sudo " . self::validator()],
				verify: "cat " . config("deployable.reload_result"),
			);
		} else {
			$checks[] = Check::pass("reload_result", "Validator has run", $result["message"] ?? "");
		}

		// --- anything Apache refused ----------------------------------------

		$rejected = self::rejectedFiles();

		if ($rejected) {
			$lines = array_map(
				fn ($entry) => "{$entry['file']}: {$entry['reason']}",
				$rejected,
			);

			$commands = array_map(
				fn ($entry) => "sudo rm -f {$dropin}/{$entry['file']}",
				$rejected,
			);

			$checks[] = Check::fail(
				"rejected_files",
				"Configs Apache accepted",
				"Apache refused " . count($rejected) . " config(s):\n" . implode("\n", $lines),
				how: "Each one was moved aside so the rest of the server kept loading. Fix the config and save the site again, or throw it away with the commands below.",
				commands: $commands,
				verify: "sudo cat " . config("deployable.reload_result"),
				detail: trim((string) @file_get_contents(config("deployable.configtest_detail"))),
			);
		} else {
			$checks[] = Check::pass("rejected_files", "Configs Apache accepted");
		}

		// --- written, but not loaded ----------------------------------------

		$pending = self::pendingFiles();

		if ($pending) {
			$names = implode(", ", array_map("basename", $pending));

			$checks[] = Check::fail(
				"pending_reload",
				"Configs have been loaded",
				"{count} has been written but Apache hasn't been told about {$names} yet.",
				commands: ["sudo systemctl start " . self::validatorUnit()],
				verify: "cat " . config("deployable.reload_result"),
			);
		} else {
			$checks[] = Check::pass("pending_reload", "Configs have been loaded");
		}

		return $checks;
	}

	/**
	 * Only shown when a site wants SSL: these are the things Let's Encrypt
	 * needs before a certificate is worth asking for.
	 *
	 * @return array<int, Check>
	 */
	public static function ssl(): array {
		$install = config("deployable.install_script");
		$certbot = config("deployable.certbot");
		$inbox = config("deployable.ssl_request_dir");
		$results = config("deployable.ssl_result_dir");
		$renewals = config("deployable.letsencrypt_dir") . "/renewal-hooks/deploy";

		$checks = [];

		if (!is_file($certbot)) {
			$checks[] = Check::fail(
				"certbot",
				"certbot",
				"certbot isn't installed, so there's nothing to ask Let's Encrypt with.",
				commands: ["sudo snap install --classic certbot"],
				verify: "{$certbot} --version",
			);
		} else {
			$checks[] = Check::pass("certbot", "certbot");
		}

		if (!is_file(config("deployable.mods_enabled") . "/ssl.load")) {
			$checks[] = Check::fail(
				"mod_ssl",
				"Apache's SSL module",
				"mod_ssl isn't enabled, so Apache can't serve anything over https.",
				how: "a2enmod turns the module on; the reload after it is what actually loads it.",
				commands: ["sudo a2enmod ssl", "sudo systemctl reload apache2"],
				verify: "apache2ctl -M | grep ssl",
			);
		} else {
			$checks[] = Check::pass("mod_ssl", "Apache's SSL module");
		}

		if (!is_dir($inbox) || !is_writable($inbox)) {
			$checks[] = Check::fail(
				"ssl_inbox",
				"Certificate request inbox",
				"Deployable can't write certificate requests to {$inbox}.",
				how: "The install script creates both directories and sets their owners.",
				commands: ["sudo bash {$install}"],
				verify: "ls -ld {$inbox}",
			);
		} else {
			$checks[] = Check::pass("ssl_inbox", "Certificate request inbox");
		}

		if (!is_dir($results) || !is_readable($results)) {
			$checks[] = Check::fail(
				"ssl_results",
				"Certificate reply box",
				"There's nowhere to read certificate results from: {$results}.",
				how: "The install script creates both directories and sets their owners.",
				commands: ["sudo bash {$install}"],
				verify: "ls -ld {$results}",
			);
		} else {
			$checks[] = Check::pass("ssl_results", "Certificate reply box");
		}

		$checks[] = self::unitCheck(
			config("deployable.units.ssl"),
			"Certificate request service",
			"Nothing is watching the request inbox, so a certificate request would never be picked up.",
			"sudo systemctl enable --now " . config("deployable.units.ssl"),
			$install,
		);

		if (!is_file(config("deployable.letsencrypt_dir") . "/options-ssl-apache.conf")) {
			$checks[] = Check::fail(
				"options_ssl",
				"Mozilla's SSL settings",
				"certbot's shared SSL settings aren't installed, so the site would get Apache's defaults instead.",
				blocking: false,
				how: "This package provides /etc/letsencrypt/options-ssl-apache.conf, which every Let's Encrypt vhost includes. Until it's there the site still works, with stricter defaults.",
				commands: ["sudo apt install python3-certbot-apache"],
				verify: "ls " . config("deployable.letsencrypt_dir") . "/options-ssl-apache.conf",
			);
		} else {
			$checks[] = Check::pass("options_ssl", "Mozilla's SSL settings");
		}

		if (!is_file("{$renewals}/00-reload-apache.sh")) {
			$checks[] = Check::fail(
				"renewal_hook",
				"Renewals reload Apache",
				"Without this hook a renewed certificate sits on disk while Apache keeps serving the old one, and the site breaks on expiry.",
				blocking: false,
				how: "certbot runs everything in that directory as root after a successful renewal.",
				commands: [
					"sudo install -d -m 0755 {$renewals}",
					"sudo install -m 0755 " . dirname(config("deployable.install_script")) . "/reload-apache-on-renewal.sh {$renewals}/00-reload-apache.sh",
				],
				verify: "ls {$renewals}/00-reload-apache.sh",
			);
		} else {
			$checks[] = Check::pass("renewal_hook", "Renewals reload Apache");
		}

		if (self::systemctl("is-active", "--quiet", "certbot.timer") !== 0
			&& self::systemctl("is-active", "--quiet", "snap.certbot.renew.timer") !== 0) {
			$checks[] = Check::fail(
				"renewal_timer",
				"Renewals are scheduled",
				"Nothing is renewing certificates, so every one of them will quietly expire.",
				blocking: false,
				how: "apt's certbot ships certbot.timer; the snap ships its own. Either will do.",
				commands: ["sudo systemctl enable --now certbot.timer", "sudo systemctl enable --now snap.certbot.renew.timer"],
				verify: "systemctl list-timers | grep -i certbot",
			);
		} else {
			$checks[] = Check::pass("renewal_timer", "Renewals are scheduled");
		}

		return $checks;
	}

	/**
	 * The project directory a hook points at. A site can't be served out of a
	 * directory that isn't there.
	 *
	 * @return array<int, Check>
	 */
	public static function forHook(Hook $hook): array {
		$directory = trim((string) $hook->directory);

		if ($directory === "") {
			return [
				Check::fail(
					"hook_directory",
					"Project directory",
					"This hook has no project directory, so there's nothing for a site to serve.",
					how: "Set one on the Edit page. It's where the deploy script works and where the document root lives.",
					commands: [],
				),
			];
		}

		if (!is_dir($directory)) {
			return [
				Check::fail(
					"hook_directory",
					"Project directory",
					"{$directory} isn't a directory on this server yet, so there's nothing to serve.",
					how: "Deploying this hook usually creates it. To make it by hand first:",
					commands: ["sudo install -d -o www-data -g www-data {$directory}"],
					verify: "ls -ld {$directory}",
				),
			];
		}

		if (!is_readable($directory)) {
			return [
				Check::fail(
					"hook_directory",
					"Project directory",
					"Apache can't read {$directory}.",
					commands: ["sudo chown -R www-data:www-data {$directory}"],
					verify: "sudo -u www-data ls {$directory}",
				),
			];
		}

		return [Check::pass("hook_directory", "Project directory")];
	}

	/**
	 * Everything that has to be true for one site to be served, including the
	 * server-wide checks - because a perfect vhost is worth nothing if the
	 * reload service never got enabled.
	 *
	 * @return array<int, Check>
	 */
	public static function forSite(Site $site): array {
		$checks = array_merge(self::server(), self::forHook($site->hook));

		$documentRoot = trim((string) $site->document_root);

		if ($documentRoot === "") {
			$checks[] = Check::fail(
				"document_root",
				"Document root",
				"There's no document root, so Apache wouldn't know what to serve.",
				blocking: false,
			);
		} else {
			if (!is_dir($documentRoot)) {
				$checks[] = Check::fail(
					"document_root",
					"Document root",
					"{$documentRoot} doesn't exist on this server.",
					how: "Deploy the project first, or point the document root at the directory that does exist.",
					commands: ["sudo install -d -o www-data -g www-data {$documentRoot}"],
					verify: "ls -ld {$documentRoot}",
				);
			} else {
				$checks[] = Check::pass("document_root", "Document root");

				if (!self::hasIndexFile($documentRoot)) {
					$checks[] = Check::fail(
						"index_file",
						"Something to serve",
						"{$documentRoot} has no index.php or index.html, so visitors would get a 403 Forbidden once a deploy lands.",
						blocking: false,
						how: "Usually this means the deploy script needs to run, or the document root should point at the project's public directory.",
					);
				} else {
					$checks[] = Check::pass("index_file", "Something to serve");
				}
			}
		}

		if ($site->type === "php") {
			$checks[] = self::socketCheck($site->php_version);
		}

		$checks[] = self::domainCheck($site);

		// Only relevant once a certificate has actually been asked for - a site
		// with no SSL shouldn't be told it's missing certbot.
		if ($site->sslWanted()) {
			$checks = array_merge($checks, self::ssl());

			$checks[] = self::certificateCheck($site);
		}

		return $checks;
	}

	/**
	 * Installed php-fpm versions, oldest first. Empty when none are installed.
	 *
	 * @return array<int, string>
	 */
	public static function phpVersions(): array {
		$versions = [];

		foreach (glob(rtrim(config("deployable.php_dir"), "/") . "/*/fpm", GLOB_ONLYDIR) ?: [] as $path) {
			$versions[] = basename(dirname($path));
		}

		usort($versions, "version_compare");

		return $versions;
	}

	/**
	 * The newest installed version, or the only sensible guess if nothing is
	 * installed - so the form always has something to submit.
	 */
	public static function defaultPhpVersion(): string {
		$versions = self::phpVersions();

		return $versions ? end($versions) : "8.3";
	}

	public static function phpSocket(string $version): string {
		return rtrim(config("deployable.php_socket_dir"), "/") . "/php{$version}-fpm.sock";
	}

	/**
	 * What deployable-vhost recorded the last time it ran, or null if it has
	 * never run. This is how the app learns what root can see.
	 */
	public static function reloadResult(): array|null {
		$path = config("deployable.reload_result");

		if (!is_file($path)) {
			return null;
		}

		$data = json_decode((string) @file_get_contents($path), true);

		return is_array($data) ? $data : null;
	}

	/**
	 * Configs sitting in the drop-in directory that Apache hasn't been given
	 * yet, by file mtime against the validator's recorded reload.
	 *
	 * @return array<int, string>
	 */
	public static function pendingFiles(): array {
		$reloaded = self::reloadResult()["reloaded_at"] ?? null;
		$reloadedAt = $reloaded === null ? null : strtotime($reloaded);

		$pending = [];

		foreach (self::confFiles() as $file) {
			if ($reloadedAt === null || (int) @filemtime($file) > $reloadedAt) {
				$pending[] = $file;
			}
		}

		return $pending;
	}

	/**
	 * Configs Apache threw out, with the reason the validator recorded.
	 *
	 * @return array<int, array{file: string, reason: string}>
	 */
	public static function rejectedFiles(): array {
		$rejected = array_map(
			fn ($entry) => ["file" => $entry["file"] ?? "", "reason" => $entry["reason"] ?? ""],
			self::reloadResult()["rejected"] ?? [],
		);

		// The validator is the authority, but a file can outlive the run that
		// rejected it (a later run may not mention it), so both get a say.
		foreach (self::sidecarFiles() as $file) {
			$name = basename($file);

			$known = array_filter($rejected, fn ($entry) => $entry["file"] === $name);

			if (!$known) {
				$rejected[] = ["file" => $name, "reason" => "Apache refused this file; see the config test output."];
			}
		}

		return $rejected;
	}

	/**
	 * Every live site config in the drop-in directory.
	 *
	 * @return array<int, string>
	 */
	public static function confFiles(): array {
		return glob(rtrim(config("deployable.vhost_dir"), "/") . "/*.conf") ?: [];
	}

	/**
	 * @return array<int, string>
	 */
	public static function sidecarFiles(): array {
		return glob(rtrim(config("deployable.vhost_dir"), "/") . "/*.conf.rejected") ?: [];
	}

	// ---------------------------------------------------------------------

	/**
	 * Whether the certificate certbot last wrote is still there.
	 *
	 * This can't be answered from PHP - /etc/letsencrypt/live is 0700 root, so
	 * a stat from www-data says "missing" whether or not the file exists - so
	 * it's read back from what root recorded when it last ran certbot, plus the
	 * vhost file that points at it.
	 */
	private static function certificateCheck(Site $site): Check {
		$result = $site->sslResult();
		$hasVhost = is_file($site->sslVhostPath());
		$state = $site->ssl_state;

		if ($state === "active" && $hasVhost) {
			$detail = $result["at"] ?? "";

			return Check::pass("certificate", "Certificate is in use", $detail !== "" ? "Issued {$detail}" : "");
		}

		if ($state === "pending") {
			return Check::fail(
				"certificate",
				"Certificate is in use",
				"A certificate request is still waiting for an answer.",
				blocking: false,
				how: "Press \"Check the certificate\" below - certbot takes a few seconds, and the answer is waiting in the results box.",
			);
		}

		if ($state === "failed") {
			return Check::fail(
				"certificate",
				"Certificate is in use",
				"Let's Encrypt refused the last request for {$site->domain}.",
				detail: trim((string) ($result["output"] ?? $site->ssl_output ?? "")),
				how: "The usual causes are DNS not pointing here yet, or port 80 being unreachable from outside. Fix that, then request the certificate again.",
				commands: ["dig +short {$site->domain}"],
				verify: "curl -s -o /dev/null -w '%{http_code}' http://{$site->domain}/.well-known/acme-challenge/test",
			);
		}

		return Check::fail(
			"certificate",
			"Certificate is in use",
			"No certificate has been requested for {$site->domain} yet, so https can't be served.",
			blocking: false,
			how: "Ask for one from the SSL panel below. The site keeps serving on http until one is in place.",
		);
	}

	private static function socketCheck(string $version): Check {
		$socket = self::phpSocket($version);

		if (file_exists($socket)) {
			return Check::pass("php_socket", "PHP {$version} is running");
		}

		$installed = self::phpVersions();

		if ($installed) {
			$problem = "PHP {$version} isn't running on this server, so requests to the site would fail.";
			$how = "The installed version(s): " . implode(", ", $installed) . ". Picking one of those in the PHP version field needs no installing.";
			$commands = [];
		} else {
			$problem = "No php-fpm is running on this server, so PHP files would be sent to visitors as plain text.";
			$how = "Install a PHP version and start it.";
			$commands = [
				"sudo apt install php{$version}-fpm",
				"sudo systemctl enable --now php{$version}-fpm",
			];
		}

		return Check::fail(
			"php_socket",
			"PHP {$version} is running",
			$problem,
			how: $how,
			commands: $commands,
			verify: "ls -l {$socket}",
		);
	}

	private static function domainCheck(Site $site): Check {
		$domain = trim((string) $site->domain);

		if ($domain === "") {
			return Check::fail(
				"domain_free",
				"Domain is free",
				"There's no domain yet, so Apache wouldn't know when to serve this site.",
				blocking: false,
			);
		}

		if (!SiteValidation::isDomain($domain)) {
			return Check::fail(
				"domain_free",
				"Domain is free",
				"'{$domain}' isn't a domain name Deployable can use for a site or a file name.",
			);
		}

		$ignore = $site->exists ? array_filter([
			$site->vhostPath(),
			$site->sslVhostPath(),
		]) : [];

		$owner = ExistingVhosts::ownerOf($domain, $ignore);

		if ($owner !== null) {
			$inDropin = str_starts_with($owner, rtrim(config("deployable.vhost_dir"), "/") . "/");

			$problem = $inDropin
				? "{$domain} is already served from {$owner}."
				: "{$domain} is already declared in {$owner}, which Apache loads before anything Deployable writes.";

			$how = $inDropin
				? "Either edit that site instead, or delete it and create it again under this hook."
				: "Two configs claiming one name is only a warning to Apache - the first one loaded wins, and the other silently never serves anything. " .
					"Either pick another name, or bring that existing config under this hook from the \"Existing site on this server\" panel below.";

			return Check::fail("domain_free", "Domain is free", $problem, how: $how);
		}

		return Check::pass("domain_free", "Domain is free");
	}

	private static function unitCheck(string $unit, string $label, string $problem, string $command, string $install): Check {
		$path = "/etc/systemd/system/{$unit}";

		if (!is_file($path)) {
			return Check::fail(
				"unit_{$unit}",
				$label,
				$problem . " The unit file isn't installed.",
				how: "The install script puts the units in place and enables them.",
				commands: ["sudo bash {$install}"],
				verify: "ls {$path}",
			);
		}

		if (self::systemctl("is-active", "--quiet", $unit) !== 0) {
			return Check::fail(
				"unit_{$unit}",
				$label,
				$problem . " The unit is installed but not running.",
				commands: [$command],
				verify: "systemctl status {$unit}",
			);
		}

		if (self::systemctl("is-enabled", "--quiet", $unit) !== 0) {
			return Check::fail(
				"unit_{$unit}",
				$label,
				$problem . " It's running now, but it won't start again after a reboot.",
				commands: ["sudo systemctl enable {$unit}"],
				verify: "systemctl is-enabled {$unit}",
			);
		}

		return Check::pass("unit_{$unit}", $label);
	}

	private static function hasIndexFile(string $directory): bool {
		foreach (["index.php", "index.html", "index.htm"] as $name) {
			if (is_file($directory . "/" . $name)) {
				return true;
			}
		}

		return false;
	}

	private static function validator(): string {
		return "/usr/local/sbin/deployable-vhost";
	}

	private static function validatorUnit(): string {
		return "deployable-vhost.service";
	}

	private static function systemctl(string ...$args): int {
		$binary = config("deployable.systemctl");

		if (!is_file($binary)) {
			return 1;
		}

		$command = implode(" ", array_map("escapeshellarg", [$binary, ...$args])) . " 2>/dev/null";

		exec($command, $output, $exit);

		return $exit;
	}

	private static function ownerOf(string $directory): string {
		if (!function_exists("posix_getpwuid") || !function_exists("fileowner")) {
			return "another user";
		}

		$owner = @posix_getpwuid(@fileowner($directory));

		return $owner["name"] ?? "another user";
	}
}
