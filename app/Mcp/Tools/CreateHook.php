<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Hooks;
use App\Models\Hook;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a new deploy hook. The slug becomes the public webhook path that triggers it, so it must be unique — unlike the web form, this tool refuses a slug another hook already uses.')]
class CreateHook extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$validated = $request->validate([
			"name" => "required|string|max:255",
			"slug" => "required|string|max:255",
			"directory" => "nullable|starts_with:/",
			"script" => "nullable|string",
		], [
			"directory.starts_with" => "The project directory must be a full path, like /var/www/example.",
		]);

		$existing = Hooks::slugOwner($validated["slug"]);

		if ($existing != null) {
			throw new ToolException(
				"Hook {$existing->id} already uses the slug '{$validated['slug']}'. " .
				"Slugs are the webhook path and aren't unique, so a duplicate would make one of them unreachable. Pick another."
			);
		}

		$hook = Hook::create([
			"name" => $validated["name"],
			"slug" => $validated["slug"],
			"directory" => $validated["directory"] ?? "",
			"script" => Hooks::normaliseScript($validated["script"] ?? ""),
		]);

		return Response::structured([
			"hook" => Hooks::detail($hook),
			"created" => true,
			"note" => "Nothing has run yet. Trigger it with trigger_deploy once you trust the script.",
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"name" => $schema->string()
				->description('Human-readable label shown on the dashboard.')
				->max(255)
				->required(),
			"slug" => $schema->string()
				->description('Unique webhook identifier. POSTing to /run/<slug> triggers a deploy without authentication.')
				->max(255)
				->required(),
			"directory" => $schema->string()
				->description('Absolute path to the project on this server, e.g. /var/www/example. Optional.'),
			"script" => $schema->string()
				->description(
					'The bash to run on deploy. It runs with no reliable working directory, so use absolute paths. ' .
					'Defaults to empty.'
				),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"hook" => $schema->object([
				"id" => $schema->integer()->required(),
				"name" => $schema->string()->required(),
				"slug" => $schema->string()->required(),
				"directory" => $schema->string()->required(),
				"webhook_url" => $schema->string()->required(),
				"script" => $schema->string()->required(),
				"script_hash" => $schema->string()->required(),
				"deployment_count" => $schema->integer()->required(),
				"last_deployed_at" => $schema->string()->nullable()->required(),
				"created_at" => $schema->string()->nullable()->required(),
				"updated_at" => $schema->string()->nullable()->required(),
				"env_file_path" => $schema->string()->nullable()->required(),
				"env_directory_exists" => $schema->boolean()->required(),
			])->required(),
			"created" => $schema->boolean()->required(),
			"note" => $schema->string()->required(),
		];
	}
}
