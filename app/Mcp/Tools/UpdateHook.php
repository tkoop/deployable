<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Hooks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Change a hook. Only the fields you pass are touched. Writing script requires expected_script_hash from a recent get_hook, so you cannot silently overwrite a script edited in the browser since you last looked.')]
#[IsIdempotent]
class UpdateHook extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Hooks::resolve(
			$request->get("hook_id") !== null ? (int) $request->get("hook_id") : null,
			$request->get("slug")
		);

		$wantsScript = $request->has("script");

		$validated = $request->validate([
			"name" => "sometimes|required|string|max:255",
			"new_slug" => "sometimes|required|string|max:255",
			"directory" => "nullable|starts_with:/",
			"script" => "sometimes|string",
			"expected_script_hash" => "nullable|string",
		], [
			"directory.starts_with" => "The project directory must be a full path, like /var/www/example.",
		]);

		if ($wantsScript) {
			Hooks::assertHashMatches(
				$hook,
				(string) ($validated["expected_script_hash"] ?? ""),
				"update_hook"
			);
		}

		if (array_key_exists("name", $validated)) {
			$hook->name = $validated["name"];
		}

		if (array_key_exists("new_slug", $validated)) {
			$conflict = Hooks::slugOwner($validated["new_slug"], $hook->id);

			if ($conflict != null) {
				throw new ToolException(
					"Hook {$conflict->id} already uses the slug '{$validated['new_slug']}'. Slugs are the " .
					"webhook path and aren't unique, so a duplicate would make one of them unreachable."
				);
			}

			$hook->slug = $validated["new_slug"];
		}

		if (array_key_exists("directory", $validated)) {
			$hook->directory = $validated["directory"] ?? "";
		}

		if ($wantsScript) {
			$hook->script = Hooks::normaliseScript($validated["script"]);
		}

		$hook->save();

		// validated() only contains what was actually sent, thanks to the sometimes
// rules — so presence here mirrors the caller's intent exactly.
		$changed = [];
		if (array_key_exists("name", $validated)) $changed[] = "name";
		if (array_key_exists("new_slug", $validated)) $changed[] = "new_slug";
		if (array_key_exists("directory", $validated)) $changed[] = "directory";
		if ($wantsScript) $changed[] = "script";

		return Response::structured([
			"hook" => Hooks::detail($hook->fresh()),
			"changed" => $changed,
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook to update. Preferred, since slugs are not unique in this app.'),
			"slug" => $schema->string()
				->description('The hook to update, by slug. Fails if the slug is ambiguous.'),
			"name" => $schema->string()->description('New name.')->max(255),
			"new_slug" => $schema->string()
				->description('Replacement slug. Must not collide with another hook.')
				->max(255),
			"directory" => $schema->string()
				->description('New absolute project directory, or an empty string to clear it.'),
			"script" => $schema->string()
				->description('The complete replacement bash script. Use append_to_hook_script to add lines instead.')
				->min(0),
			"expected_script_hash" => $schema->string()
				->description('Required whenever script is sent. The script_hash from your last get_hook.')
				->min(12)
				->max(12),
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
			"changed" => $schema->array()->required()
				->description('Which fields this call wrote.'),
		];
	}
}
