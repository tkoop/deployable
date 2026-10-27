<?php

namespace App\Mcp\Tools;

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

#[Description('Give a hook an Apache site: the domain it answers on and the directory Apache serves. A site is always a property of one hook, so hook_id is required. Refuses and returns problems[] when the server is not ready for it.')]
class CreateSite extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);

		if ($hook->site !== null) {
			throw new ToolException(
				"Hook {$hook->id} already has a site ({$hook->site->domain}). Use update_site to change it."
			);
		}

		$validated = $request->validate(
			SiteValidation::rules(),
			SiteValidation::messages()
		);

		$site = new Site(["hook_id" => $hook->id]);
		$site->fill($validated);
		$site->aliases = SiteValidation::normalizeAliases($request->get("aliases", ""));

		$problems = Sites::problems($site);

		if ($problems) {
			throw new ToolException(
				"The site was not created; the server is not ready for it:\n\n" .
				implode("\n\n", $problems)
			);
		}

		$site->save();

		$write = $site->apache()->write();

		return Response::structured([
			"created" => true,
			"accepted" => $write["success"],
			"site" => Sites::detail($site),
			"output" => $write["output"],
			"problems" => Sites::problems($site),
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook this site belongs to. Required: a site is a property of one hook.'),
			"slug" => $schema->string()
				->description('Hook slug as an alternative to hook_id. Fails if ambiguous.'),
			"domain" => $schema->string()->required()
				->description('The primary domain, e.g. example.com.'),
			"aliases" => $schema->string()
				->description('Additional host names, one per line or comma-separated.'),
			"document_root" => $schema->string()->required()
				->description('Absolute path Apache serves, e.g. /var/www/example/public.'),
			"type" => $schema->string()
				->description('php or static. Defaults to php.'),
			"php_version" => $schema->string()
				->description('PHP version, e.g. 8.3. Required when type is php.'),
			"ssl_email" => $schema->string()
				->description('Email for Let\'s Encrypt expiry warnings.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"created" => $schema->boolean()->required(),
			"accepted" => $schema->boolean()->required(),
			"site" => $schema->object()->required(),
			"output" => $schema->string()->required(),
			"problems" => $schema->array()->required(),
		];
	}
}
