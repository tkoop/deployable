<?php

namespace App\Mcp\Tools;

use App\Helpers\SiteValidation;
use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Sites;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Change a site. Only the fields you pass are touched. Saving rewrites the Apache config from Deployable\'s template, so anything hand-edited in the vhost file is replaced.')]
#[IsIdempotent]
class UpdateSite extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);
		$site = Sites::require($hook);

		$validated = $request->validate(
			SiteValidation::rules($site->id),
			SiteValidation::messages()
		);

		$site->fill($validated);

		if ($request->has("aliases")) {
			$site->aliases = SiteValidation::normalizeAliases($request->get("aliases"));
		}

		if ($request->has("enabled")) {
			$site->enabled = (bool) $request->get("enabled");
		}

		$problems = Sites::problems($site);

		if ($problems) {
			throw new ToolException(
				"The site was not saved; the server is not ready for it:\n\n" .
				implode("\n\n", $problems)
			);
		}

		$site->save();

		$write = $site->apache()->write();

		return Response::structured([
			"updated" => true,
			"accepted" => $write["success"],
			"site" => Sites::detail($site),
			"output" => $write["output"],
			"problems" => Sites::problems($site),
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook whose site to update. Required.'),
			"slug" => $schema->string()
				->description('Hook slug as an alternative to hook_id.'),
			"domain" => $schema->string()
				->description('The primary domain.'),
			"aliases" => $schema->string()
				->description('Additional host names, one per line or comma-separated. Replaces the current list.'),
			"document_root" => $schema->string()
				->description('Absolute path Apache serves.'),
			"type" => $schema->string()
				->description('php or static.'),
			"php_version" => $schema->string()
				->description('PHP version, e.g. 8.3.'),
			"enabled" => $schema->boolean()
				->description('Whether the site is enabled (served).'),
			"ssl_email" => $schema->string()
				->description('Email for Let\'s Encrypt expiry warnings.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"updated" => $schema->boolean()->required(),
			"accepted" => $schema->boolean()->required(),
			"site" => $schema->object()->required(),
			"output" => $schema->string()->required(),
			"problems" => $schema->array()->required(),
		];
	}
}
