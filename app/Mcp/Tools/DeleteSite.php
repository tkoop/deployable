<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Sites;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a hook\'s site: remove its Apache config from the drop-in directory and the site record. The project directory and its files are left alone. There is no way to reach a site without the hook it belongs to.')]
class DeleteSite extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);
		$site = Sites::require($hook);

		$domain = $site->domain;

		$output = $site->apache()->delete();

		if (!$output["success"]) {
			throw new ToolException($output["output"]);
		}

		$site->delete();

		return Response::structured([
			"deleted" => true,
			"domain" => $domain,
			"output" => $output["output"] . "\n\nThe hook and its directory were left alone.",
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook whose site to delete. Required.'),
			"slug" => $schema->string()
				->description('Hook slug as an alternative to hook_id.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"deleted" => $schema->boolean()->required(),
			"domain" => $schema->string()->required(),
			"output" => $schema->string()->required(),
		];
	}
}
