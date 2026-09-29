<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Hooks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add lines to the end of a hook script without rewriting it. Takes expected_script_hash, so it will refuse rather than append to a script that changed underneath you. Not idempotent — calling it twice appends twice.')]
class AppendToHookScript extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Hooks::resolve(
			$request->get("hook_id") !== null ? (int) $request->get("hook_id") : null,
			$request->get("slug")
		);

		$validated = $request->validate([
			"lines" => "required|string",
			"expected_script_hash" => "required|string",
		]);

		Hooks::assertHashMatches($hook, $validated["expected_script_hash"], "append_to_hook_script");

		$before = $hook->script;

		// rtrim so the separator is exactly one newline whether or not the
		// stored script already ended with one; normaliseScript then puts a
		// trailing newline back on the result.
		$hook->script = Hooks::normaliseScript(rtrim($before, "\n") . "\n" . $validated["lines"]);
		$hook->save();

		$added = $hook->script === $before ? [] : explode("\n", rtrim($validated["lines"], "\n"));

		return Response::structured([
			"hook_id" => $hook->id,
			"script" => $hook->script,
			"script_hash" => Hooks::scriptHash($hook->script),
			"lines_added" => $added,
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook whose script to append to. Preferred, since slugs are not unique.')
				->required(),
			"lines" => $schema->string()
				->description('The bash to add, verbatim. Newlines are fine and become separate lines.')
				->required(),
			"expected_script_hash" => $schema->string()
				->description('The script_hash from your last get_hook. Required, and a mismatch aborts the append.')
				->min(12)
				->max(12)
				->required(),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()->required(),
			"script" => $schema->string()->required()
				->description('The full script after appending.'),
			"script_hash" => $schema->string()->required()
				->description('The new hash. Use it for your next write.'),
			"lines_added" => $schema->array()->required(),
		];
	}
}
