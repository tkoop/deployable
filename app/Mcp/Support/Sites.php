<?php

namespace App\Mcp\Support;

use App\Helpers\Check;
use App\Helpers\ExistingVhosts;
use App\Helpers\Preflight;
use App\Helpers\SiteValidation;
use App\Helpers\SslCertificates;
use App\Mcp\Exceptions\ToolException;
use App\Models\Hook;
use App\Models\Site;

/**
 * Shared lookups and serialisation for the site tools.
 *
 * A site is a property of a hook, so every tool here takes a hook_id (or
 * resolves one from a slug) and refuses to act when the hook has no site.
 */
class Sites {
	/**
	 * Resolve a hook from the request, requiring hook_id or slug.
	 *
	 * @throws ToolException
	 */
	public static function resolveHook($request): Hook {
		$hookId = $request->get("hook_id");
		$slug = $request->get("slug");

		if ($hookId === null && ($slug === null || trim((string) $slug) === "")) {
			throw new ToolException("This tool needs a hook_id: a site is always a property of one hook.");
		}

		return \App\Mcp\Support\Hooks::resolve(
			$hookId !== null ? (int) $hookId : null,
			$slug !== null ? (string) $slug : null
		);
	}

	/**
	 * The site on a hook, or null when it has none.
	 */
	public static function on(Hook $hook): ?Site {
		return $hook->site;
	}

	/**
	 * The site on a hook, failing loudly when there isn't one.
	 *
	 * @throws ToolException
	 */
	public static function require(Hook $hook): Site {
		$site = $hook->site;

		if ($site === null) {
			throw new ToolException("Hook {$hook->id} has no site. Use create_site to give it one.");
		}

		return $site;
	}

	/**
	 * Blocking preflight problems for a site, as plain text lines.
	 *
	 * @return array<int, string>
	 */
	public static function problems(Site $site): array {
		return array_map(
			fn (Check $check) => $check->instructions(),
			Check::blockers($site->preflight())
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function summary(Site $site): array {
		return [
			"id" => $site->id,
			"hook_id" => $site->hook_id,
			"domain" => $site->domain,
			"aliases" => $site->aliases ?? [],
			"hosts" => $site->allHosts(),
			"document_root" => $site->document_root,
			"type" => $site->type,
			"php_version" => $site->php_version,
			"enabled" => $site->enabled,
			"ssl_state" => $site->ssl_state,
			"ssl_email" => $site->ssl_email,
			"vhost_path" => $site->vhostPath(),
			"ssl_vhost_path" => $site->sslVhostPath(),
			"config_exists" => file_exists($site->vhostPath()),
			"ssl_config_exists" => file_exists($site->sslVhostPath()),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function detail(Site $site): array {
		return self::summary($site) + [
			"ssl_output" => $site->ssl_output,
			"created_at" => $site->created_at?->toIso8601String(),
			"updated_at" => $site->updated_at?->toIso8601String(),
			"problems" => self::problems($site),
			"preflight" => array_map(fn (Check $check) => $check->toArray(), $site->preflight()),
			"status" => $site->apache()->status(),
		];
	}
}
