<?php

namespace App\Mcp\Tools;

use App\Helpers\ExistingVhosts;
use App\Helpers\SiteValidation;
use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Hooks;
use App\Mcp\Support\Sites;
use App\Models\Site;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the Apache vhosts already on this server, whether or not Deployable manages them. Use this to find an existing site to connect to a hook.')]
#[IsReadOnly]
class ListExistingVhosts extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$vhosts = [];

		foreach (ExistingVhosts::all() as $vhost) {
			$claimed = Site::where("domain", $vhost["domain"])->first();

			$vhosts[] = [
				"domain" => $vhost["domain"],
				"aliases" => $vhost["aliases"] ?? [],
				"hosts" => $vhost["hosts"] ?? [$vhost["domain"]],
				"file" => $vhost["file"],
				"basename" => $vhost["basename"],
				"document_root" => $vhost["document_root"],
				"in_deployable" => $vhost["in_dropin"],
				"enabled" => $vhost["enabled"],
				"ssl" => $vhost["ssl"],
				"claimed_by_hook" => $claimed?->hook_id,
				"instructions" => ExistingVhosts::instructions($vhost),
			];
		}

		return Response::structured(["vhosts" => $vhosts, "count" => count($vhosts)]);
	}

	public function schema(JsonSchema $schema): array {
		return [];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"vhosts" => $schema->array()->required(),
			"count" => $schema->integer()->required(),
		];
	}
}

#[Description('Connect a hook to an existing vhost already copied into the drop-in directory. The config is left untouched on adoption; saving the site later rewrites it from Deployable\'s template.')]
class ConnectExistingVhost extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);

		$domain = trim((string) $request->get("domain", ""));

		if (!SiteValidation::isDomain($domain)) {
			throw new ToolException("'{$domain}' isn't a domain name.");
		}

		if (Site::where("domain", $domain)->exists()) {
			$owner = Site::where("domain", $domain)->first();
			throw new ToolException(
				"{$domain} is already a site on hook {$owner->hook_id}. One hook serves one domain."
			);
		}

		$dir = rtrim(config("deployable.vhost_dir"), "/");
		$file = "{$dir}/{$domain}.conf";

		if (!is_file($file)) {
			throw new ToolException(
				"{$domain} hasn't been copied into {$dir} yet. " .
				"Run the instructions from list_existing_vhosts first, then connect."
			);
		}

		$sniffed = $this->sniff($file);

		$site = Site::create([
			"hook_id" => $hook->id,
			"domain" => $domain,
			"aliases" => $this->sniffAliases($file, $domain),
			"document_root" => ExistingVhosts::documentRootOf($file) ?: ($hook->directory ?: $dir),
			"type" => $sniffed["type"],
			"php_version" => $sniffed["php_version"],
			"enabled" => true,
			"ssl_state" => is_file("{$dir}/{$domain}-le-ssl.conf") ? "active" : "none",
		]);

		return Response::structured([
			"connected" => true,
			"site" => Sites::detail($site),
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()->description('The hook to connect the vhost to. Required.'),
			"slug" => $schema->string()->description('Hook slug as an alternative to hook_id.'),
			"domain" => $schema->string()->required()
				->description('The domain of the vhost already copied into the drop-in directory.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"connected" => $schema->boolean()->required(),
			"site" => $schema->object()->required(),
		];
	}

	private function sniff(string $file): array {
		$contents = (string) @file_get_contents($file);

		if (preg_match('/php(\d+\.\d+)-fpm\.sock/', $contents, $matches)) {
			return ["type" => "php", "php_version" => $matches[1]];
		}

		if (preg_match('/php|SetHandler|AddHandler|Action\s/i', $contents)) {
			return ["type" => "php", "php_version" => \App\Helpers\Preflight::defaultPhpVersion()];
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
}
