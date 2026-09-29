<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Hooks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one hook in full, including its deploy script. Returns the script_hash you pass back to update_hook or append_to_hook_script so you cannot clobber a script you have not read.')]
#[IsReadOnly]
class GetHook extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Hooks::resolve(
			$request->get("hook_id") !== null ? (int) $request->get("hook_id") : null,
			$request->get("slug")
		);

		return Response::structured(["hook" => Hooks::detail($hook)]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook id. Preferred, since slugs are not unique in this app.'),
			"slug" => $schema->string()
				->description('The hook slug, as an alternative to hook_id. Fails if it matches more than one hook.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"hook" => $schema->object([
				"id" => $schema->integer()->required(),
				"name" => $schema->string()->required(),
				"slug" => $schema->string()->required(),
				"directory" => $schema->string()->required()
					->description('Absolute path to the project directory, or empty when unset.'),
				"webhook_url" => $schema->string()->required()
					->description('The unauthenticated URL that triggers a deploy for this hook.'),
				"script" => $schema->string()->required()
					->description('The bash run on deploy. It has no reliable working directory: use absolute paths.'),
				"script_hash" => $schema->string()->required()
					->description('Digest of the current script. Pass this back when writing to it.'),
				"deployment_count" => $schema->integer()->required(),
				"last_deployed_at" => $schema->string()->nullable()->required(),
				"created_at" => $schema->string()->nullable()->required(),
				"updated_at" => $schema->string()->nullable()->required(),
				"env_file_path" => $schema->string()->nullable()->required(),
				"env_directory_exists" => $schema->boolean()->required(),
			])->required(),
		];
	}
}
