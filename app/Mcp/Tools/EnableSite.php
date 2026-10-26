<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Sites;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Enable a site: rename its *.conf.disabled back to *.conf so Apache serves it again.')]
class EnableSite extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);
		$site = Sites::require($hook);

		$site->enabled = true;
		$output = $site->apache()->enable();

		if ($output["success"]) {
			$site->save();
		}

		return Response::structured([
			"success" => $output["success"],
			"site" => Sites::detail($site->fresh()),
			"output" => $output["output"],
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()->description('The hook whose site to enable. Required.'),
			"slug" => $schema->string()->description('Hook slug as an alternative to hook_id.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"success" => $schema->boolean()->required(),
			"site" => $schema->object()->required(),
			"output" => $schema->string()->required(),
		];
	}
}

#[Description('Disable a site: rename its *.conf to *.conf.disabled so Apache stops serving it, without deleting the config.')]
class DisableSite extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);
		$site = Sites::require($hook);

		$site->enabled = false;
		$output = $site->apache()->disable();

		if ($output["success"]) {
			$site->save();
		}

		return Response::structured([
			"success" => $output["success"],
			"site" => Sites::detail($site->fresh()),
			"output" => $output["output"],
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()->description('The hook whose site to disable. Required.'),
			"slug" => $schema->string()->description('Hook slug as an alternative to hook_id.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"success" => $schema->boolean()->required(),
			"site" => $schema->object()->required(),
			"output" => $schema->string()->required(),
		];
	}
}
