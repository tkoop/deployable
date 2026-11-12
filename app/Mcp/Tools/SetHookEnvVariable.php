<?php

namespace App\Mcp\Tools;

use App\Helpers\ConfigCache;
use App\Helpers\EnvFile;
use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Hooks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Add a variable to a hook project\'s .env file, but only when it is not already defined there. An existing variable is never overwritten, so this is safe to call repeatedly. The variable name must be a valid shell-style identifier.')]
#[IsIdempotent]
class SetHookEnvVariable extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Hooks::resolve(
			$request->get("hook_id") !== null ? (int) $request->get("hook_id") : null,
			$request->get("slug")
		);

		$validated = $request->validate([
			"name" => ["required", "string", "regex:/^[A-Za-z_][A-Za-z0-9_]*$/"],
			"value" => "required|string",
			"refresh_config" => "sometimes|boolean",
		], [
			"name.regex" => "The variable name must start with a letter or underscore and contain only letters, digits, and underscores.",
		]);

		$envFile = $hook->envFile();

		if (!$envFile->directoryExists()) {
			throw new ToolException(
				"Hook {$hook->id} has no project directory on disk, so there is no .env to edit. " .
				"Set the hook's directory first."
			);
		}

		$name = $validated["name"];
		$existing = $envFile->variableNames();

		if (in_array($name, $existing, true)) {
			return Response::structured([
				"hook" => Hooks::detail($hook->fresh()),
				"name" => $name,
				"added" => false,
				"reason" => "already_exists",
				"variable_names" => $existing,
				"config" => null,
			]);
		}

		$contents = $envFile->get();
		if ($contents !== "" && !str_ends_with($contents, "\n")) {
			$contents .= "\n";
		}
		$contents .= $name . "=" . $this->quoteValue($validated["value"]) . "\n";

		if (!$envFile->set($contents)) {
			throw new ToolException("Couldn't write the .env file. Is the directory writable?");
		}

		$refresh = array_key_exists("refresh_config", $validated)
			? (bool) $validated["refresh_config"]
			: true;

		$config = null;
		if ($refresh) {
			$config = ConfigCache::forHook($hook)->refresh();
		}

		return Response::structured([
			"hook" => Hooks::detail($hook->fresh()),
			"name" => $name,
			"added" => true,
			"reason" => "added",
			"variable_names" => $envFile->variableNames(),
			"config" => $config,
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook whose project .env to edit. Preferred, since slugs are not unique in this app.'),
			"slug" => $schema->string()
				->description('The hook to edit, by slug. Fails if the slug is ambiguous.'),
			"name" => $schema->string()
				->description('The variable name to add. Must be a valid identifier (letters, digits, underscores; not starting with a digit).')
				->required(),
			"value" => $schema->string()
				->description('The value to write. It is quoted automatically when it contains spaces or special characters.')
				->required(),
			"refresh_config" => $schema->boolean()
				->description('Rebuild the project\'s cached config so the new value takes effect. Defaults to true.'),
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
			"name" => $schema->string()->required(),
			"added" => $schema->boolean()->required(),
			"reason" => $schema->string()->required()
				->description('added or already_exists.'),
			"variable_names" => $schema->array()->required()
				->description('The variable names now in the .env file (values are never returned).'),
			"config" => $schema->object([
				"success" => $schema->boolean()->required(),
				"output" => $schema->string()->required(),
			])->nullable()->required(),
		];
	}

	/**
	 * Quote a value the way a .env parser expects, leaving simple values bare.
	 */
	private function quoteValue(string $value): string {
		if ($value === "") {
			return '""';
		}

		if (preg_match('/^[A-Za-z0-9_\-\.\/:@]+$/', $value)) {
			return $value;
		}

		$escaped = str_replace(["\\", "\"", "\n", "\r"], ["\\\\", "\\\"", "\\n", "\\r"], $value);
		return '"' . $escaped . '"';
	}
}
