<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Deployments;
use App\Mcp\Support\Hooks;
use App\Models\Deployment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Run a hook\'s deploy script now. Returns immediately with a deployment id; pass wait=true to block until it finishes and report whether the script exited 0. Be aware this executes whatever bash is in the hook script.')]
#[IsOpenWorld(true)]
class TriggerDeploy extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Hooks::resolve(
			$request->get("hook_id") !== null ? (int) $request->get("hook_id") : null,
			$request->get("slug")
		);

		$deployment = $hook->start();

		$wait = (bool) $request->get("wait", false);
		$timeout = min(max((int) ($request->get("timeout") ?? 60), 1), 300);
		$tailLines = min(max((int) ($request->get("tail_lines") ?? 50), 0), 500);

		if ($wait) {
			$this->waitFor($deployment, $timeout);
		}

		$deployment->refresh()->load("hook");

		$output = $tailLines > 0
			? Deployments::readOutput($deployment, tailLines: $tailLines)
			: null;

		$succeeded = $deployment->succeeded();

		return Response::structured([
			"deployment" => Hooks::deploymentSummary($deployment) + [
				"waited" => $wait,
			],
			"timed_out" => $wait && $deployment->isRunning(),
			"succeeded" => $succeeded,
			"outcome_note" => $wait && $deployment->isRunning()
				? "Still running when the wait ran out. Poll with get_deployment."
				: ($succeeded === false
					? "The deploy script exited {$deployment->exit_code}. Read the output for why."
					: ($succeeded === null
						? "The deploy finished but left no exit status, so whether it worked is unknown. Read the output."
						: "The deploy script exited 0.")),
			"output" => $output === null ? null : [
				"exists" => $output["exists"],
				"truncated" => $output["truncated"],
				"content" => $output["content"],
			],
		]);
	}

	/**
	 * Poll until the deployment leaves the started/running states.
	 *
	 * Deliberately a fixed sleep rather than a queue: the state is only ever
	 * advanced by two artisan calls made from inside the generated deploy
	 * script, so there is nothing to subscribe to.
	 */
	private function waitFor(Deployment $deployment, int $timeout): void {
		$deadline = microtime(true) + $timeout;

		do {
			usleep(500000);

			$deployment->refresh();

			if (!$deployment->isRunning()) {
				return;
			}
		} while (microtime(true) < $deadline);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()
				->description('The hook to deploy. Preferred, since slugs are not unique in this app.'),
			"slug" => $schema->string()
				->description('The hook to deploy, by slug. Fails if the slug is ambiguous.'),
			"wait" => $schema->boolean()
				->description('Block until the deploy finishes instead of returning at once.')
				->default(false),
			"timeout" => $schema->integer()
				->description('Seconds to wait when wait is true. Defaults to 60, capped at 300.')
				->default(60),
			"tail_lines" => $schema->integer()
				->description('Lines of output to include in the response. 0 to omit.')
				->default(50),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"deployment" => $schema->object([
				"id" => $schema->integer()->required(),
				"hook_id" => $schema->integer()->required(),
				"hook_name" => $schema->string()->nullable()->required(),
				"state" => $schema->string()->required(),
				"succeeded" => $schema->boolean()->nullable()->required(),
				"exit_code" => $schema->integer()->nullable()->required(),
				"created_at" => $schema->string()->nullable()->required(),
				"ended_at" => $schema->string()->nullable()->required(),
				"duration_seconds" => $schema->integer()->nullable()->required(),
				"waited" => $schema->boolean()->required(),
			])->required(),
			"timed_out" => $schema->boolean()->required(),
			"succeeded" => $schema->boolean()->nullable()->required()
				->description('Whether the deploy worked. Null while it is still running, or when the exit status was never recorded.'),
			"outcome_note" => $schema->string()->required(),
			"output" => $schema->object([
				"exists" => $schema->boolean()->required(),
				"truncated" => $schema->boolean()->required(),
				"content" => $schema->string()->required(),
			])->nullable()->required(),
		];
	}
}
