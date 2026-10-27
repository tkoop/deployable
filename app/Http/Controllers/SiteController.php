<?php

namespace App\Http\Controllers;

use App\Helpers\Check;
use App\Helpers\ExistingVhosts;
use App\Helpers\Preflight;
use App\Helpers\SslCertificates;
use App\Helpers\SiteValidation;
use App\Models\Hook;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * The Apache site a hook's directory is served as.
 *
 * Every route here hangs off /hook/{hook}: there is no way to reach a site
 * without going through the hook it belongs to, because that's what a site is -
 * the thing this directory is served as. The same goes for an existing vhost on
 * the server: you can't edit one from Deployable, you can only be told which
 * files to copy into the site directory so a hook can adopt it.
 */
class SiteController extends Controller {

	public function errorLog(Request $request, Hook $hook) {
		$site = $hook->site;

		if ($site !== null) {
			$path = $site->errorLogPath();
		} else {
			// A hook with no site yet has no Apache error log to show, so
			// fall back to Deployable's own Laravel log rather than 404ing.
			$path = storage_path("logs/laravel.log");
		}

		$contents = "";

		if (is_file($path)) {
			$raw = @file_get_contents($path);

			if ($raw !== false) {
				$contents = $raw;
			}
		}

		// Deployable's own application log (Laravel/Monolog). Kept separate
		// from the PHP/Apache error log above so both are visible.
		$appPath = (string) (config("logging.channels.single.path") ?: storage_path("logs/laravel.log"));
		$appContents = "";

		if (is_file($appPath)) {
			$raw = @file_get_contents($appPath);

			if ($raw !== false) {
				$appContents = $raw;
			}
		}

		return view("hookErrorLog", [
			"hook" => $hook,
			"site" => $site,
			"path" => $path,
			"contents" => $contents,
			"exists" => is_file($path),
			"appPath" => $appPath,
			"appContents" => $appContents,
			"appExists" => is_file($appPath),
		]);
	}
	public function view(Request $request, Hook $hook) {
		$site = $hook->site;

		return $this->page($hook, $site, $site ?? $this->provisional($hook));
	}

	public function save(Request $request, Hook $hook) {
		$site = $hook->site ?? new Site(["hook_id" => $hook->id]);
		$isNew = !$site->exists;

		$validated = $request->validate(
			SiteValidation::rules($isNew ? null : $site->id),
			SiteValidation::messages()
		);

		$site->fill($validated);
		$site->aliases = SiteValidation::normalizeAliases($request->input("aliases"));

		$blockers = Check::blockers($site->preflight());

		if ($blockers) {
			return back()
				->withInput()
				->withErrors(["base" => "The site wasn't saved - fix the checklist above first."])
				->with("outputOk", false)
				->with("output", "The site wasn't saved.\n\n" .
					implode("\n\n", array_map(fn (Check $check) => $check->instructions(), $blockers)));
		}

		$site->save();

		$output = $site->apache()->write();

		if ($isNew) {
			$output["output"] = "Created the site for {$site->domain}.\n\n" . $output["output"];
		}

		return $this->flash($output);
	}

	public function action(Request $request, Hook $hook) {
		$site = $hook->site;

		if ($site === null) {
			return $this->flash([
				"success" => false,
				"output" => "There's no site for this hook yet, so there's nothing to act on.",
			]);
		}

		$actions = [
			"enable" => "enableSite",
			"disable" => "disableSite",
			"delete_site" => "deleteSite",
			"request_ssl" => "requestCertificate",
			"check_ssl" => "checkCertificate",
			"remove_ssl" => "removeCertificate",
		];

		foreach ($actions as $input => $method) {
			if ($request->has($input)) {
				return $this->{$method}($site);
			}
		}

		return $this->flash(["success" => false, "output" => "No action was recognised."]);
	}

	public function connect(Request $request, Hook $hook) {
		$domain = trim((string) $request->input("domain", ""));
		$dir = rtrim(config("deployable.vhost_dir"), "/");

		if (!\App\Helpers\SiteValidation::isDomain($domain)) {
			return $this->flash(["success" => false, "output" => "'{$domain}' isn't a domain name."]);
		}

		$claimed = Site::with("hook")->where("domain", $domain)->first();

		if ($claimed !== null) {
			$owner = $claimed->hook?->name ?? "hook #{$claimed->hook_id}";

			return $this->flash([
				"success" => false,
				"output" => "{$domain} is already a site, on the \"{$owner}\" hook. " .
					"One hook serves one domain; edit that one instead.",
			]);
		}

		$file = "{$dir}/{$domain}.conf";

		if (!is_file($file)) {
			return $this->flash([
				"success" => false,
				"output" => "{$domain} hasn't been copied into {$dir} yet.\n\n" .
					"Run the commands shown for {$domain} in \"Existing site on this server\" below, " .
					"then press Connect again.",
			]);
		}

		$sniffed = $this->sniff($file);

		Site::create([
			"hook_id" => $hook->id,
			"domain" => $domain,
			"aliases" => $this->sniffAliases($file, $domain),
			"document_root" => ExistingVhosts::documentRootOf($file) ?: ($hook->directory ?: $dir),
			"type" => $sniffed["type"],
			"php_version" => $sniffed["php_version"],
			"enabled" => true,
			"ssl_state" => is_file("{$dir}/{$domain}-le-ssl.conf") ? "active" : "none",
		]);

		return $this->flash([
			"success" => true,
			"output" => "Connected {$domain} to this hook. Its existing config was left exactly as it is.\n\n" .
				"It's now on the checklist above, and saving it from here on rewrites the " .
				"config from Deployable's template.",
		]);
	}

	private function enableSite(Site $site) {
		$site->enabled = true;
		$output = $site->apache()->enable();

		if ($output["success"]) {
			$site->save();
		}

		return $this->flash($output);
	}

	private function disableSite(Site $site) {
		$site->enabled = false;
		$output = $site->apache()->disable();

		if ($output["success"]) {
			$site->save();
		}

		return $this->flash($output);
	}

	private function deleteSite(Site $site) {
		$output = $site->apache()->delete();

		if (!$output["success"]) {
			return $this->flash($output);
		}

		$site->delete();

		return $this->flash([
			"success" => true,
			"output" => $output["output"] . "\n\nThe hook and its directory were left alone - " .
				"nothing outside the site directory was touched.",
		]);
	}

	private function requestCertificate(Site $site) {
		return $this->flash(SslCertificates::request($site));
	}

	private function checkCertificate(Site $site) {
		return $this->flash(SslCertificates::status($site));
	}

	private function removeCertificate(Site $site) {
		SslCertificates::withdraw($site);

		$output = $site->apache()->write();

		return $this->flash([
			"success" => $output["success"],
			"output" => "HTTPS is off for {$site->domain}. The certificate itself was left on " .
				"disk for certbot to keep renewing; only Apache stopped using it.\n\n" . $output["output"],
		]);
	}

	private function page(Hook $hook, Site|null $site, Site $subject) {
		return view("hookSite", [
			"hook" => $hook,
			"site" => $site,
			"subject" => $subject,
			"checks" => $subject->preflight(),
			"phpVersions" => Preflight::phpVersions(),
			"candidates" => $this->candidates($hook, $site),
			"status" => $site?->apache()->status(),
		]);
	}

	private function provisional(Hook $hook): Site {
		$directory = trim((string) $hook->directory);

		return new Site([
			"hook_id" => $hook->id,
			"domain" => (string) old("domain", ""),
			"document_root" => (string) old("document_root", $this->defaultRoot($hook)),
			"type" => (string) old("type", "php"),
			"php_version" => (string) old("php_version", Preflight::defaultPhpVersion()),
			"ssl_state" => "none",
		]);
	}

	private function defaultRoot(Hook $hook): string {
		$directory = trim((string) $hook->directory);

		if ($directory === "") {
			return "";
		}

		return is_dir("{$directory}/public") ? "{$directory}/public" : $directory;
	}

	private function candidates(Hook $hook, Site|null $site): array {
		$claimed = [];

		foreach (Site::all() as $other) {
			$claimed[strtolower($other->domain)] = $other;
		}

		$candidates = [];

		foreach (ExistingVhosts::all() as $vhost) {
			$key = strtolower($vhost["domain"]);

			if ($site !== null && strtolower($site->domain) === $key) {
				continue;
			}

			if (isset($claimed[$key])) {
				$owner = $claimed[$key];
				$ownerHook = $owner->hook_id === $hook->id
					? "another site"
					: ($owner->hook?->name ?? "hook #{$owner->hook_id}");

				$candidates[] = $vhost + ["claimed_by" => $ownerHook, "commands" => []];
				continue;
			}

			$candidates[] = $vhost + [
				"claimed_by" => null,
				"commands" => ExistingVhosts::instructions($vhost),
			];
		}

		usort($candidates, fn ($a, $b) => [$b["in_dropin"], $a["domain"]] <=> [$a["in_dropin"], $b["domain"]]);

		return $candidates;
	}

	private function sniff(string $file): array {
		$contents = (string) @file_get_contents($file);

		if (preg_match('/php(\d+\.\d+)-fpm\.sock/', $contents, $matches)) {
			return ["type" => "php", "php_version" => $matches[1]];
		}

		if (preg_match('/php|SetHandler|AddHandler|Action\s/i', $contents)) {
			return ["type" => "php", "php_version" => Preflight::defaultPhpVersion()];
		}

		return ["type" => "static", "php_version" => ""];
	}

	private function sniffAliases(string $file, string $primary): array {
		$contents = (string) @file_get_contents($file);
		$aliases = [];

		if (preg_match_all('/^\s*ServerAlias\s+(.+)$/mi', $contents, $matches)) {
			foreach ($matches[1] as $line) {
				foreach (preg_split('/\s+/', trim($line)) ?: [] as $host) {
					$host = strtolower(trim($host));

					if ($host !== "" && $host !== strtolower($primary) && SiteValidation::isDomain($host)) {
						$aliases[] = $host;
					}
				}
			}
		}

		return array_values(array_unique($aliases));
	}

	private function flash(array $output) {
		return back()
			->with("outputOk", $output["success"])
			->with("output", $output["output"]);
	}
}
