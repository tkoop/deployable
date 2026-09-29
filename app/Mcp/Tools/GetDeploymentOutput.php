<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Deployments;
use App\Models\Deployment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description("A deployment's raw console output. Reads the same file the web UI renders, but as plain text with ANSI colour codes intact rather than HTML. Trims from the end of the log by default.")]
#[IsReadOnly]
class GetDeploymentOutput extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$deployment = Deployment::find((int) $request->get("deployment_id"));

		if ($deployment == null) {
			throw new ToolException("There's no deployment with id {$request->get('deployment_id')}.");
		}

		$tailLines = $request->get("tail_lines");
		$maxBytes = $request->get("max_bytes");

		$output = Deployments::readOutput(
			$deployment,
			$tailLines !== null ? (int) $tailLines : null,
			$maxBytes !== null ? (int) $maxBytes : null,
		);

		return Response::structured([
			"deployment_id" => $deployment->id,
			"hook_id" => $deployment->hook_id,
			"state" => $deployment->state,
			"succeeded" => $deployment->succeeded(),
			"exit_code" => $deployment->exit_code,
			"still_running" => $deployment->isRunning(),
			"output_exists" => $output["exists"],
			"path" => $output["path"],
			"bytes" => $output["bytes"],
			"total_bytes" => $output["total_bytes"],
			"truncated" => $output["truncated"],
			"content" => $output["content"],
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"deployment_id" => $schema->integer()
				->description('The deployment id.')
				->required(),
			"tail_lines" => $schema->integer()
				->description('Return only the last N lines. Omit for the whole log.')
				->default(200),
			"max_bytes" => $schema->integer()
				->description('Hard cap on the returned text, in bytes, taken from the end. Defaults to 65536.')
				->default(65536),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"deployment_id" => $schema->integer()->required(),
			"hook_id" => $schema->integer()->required(),
			"state" => $schema->string()->required(),
			"succeeded" => $schema->boolean()->nullable()->required(),
			"exit_code" => $schema->integer()->nullable()->required(),
			"still_running" => $schema->boolean()->required(),
			"output_exists" => $schema->boolean()->required()
				->description('False until the deploy script has written anything at all.'),
			"path" => $schema->string()->required(),
			"bytes" => $schema->integer()->required(),
			"total_bytes" => $schema->integer()->required(),
			"truncated" => $schema->boolean()->required(),
			"content" => $schema->string()->required()
				->description('Raw output, possibly containing ANSI escape codes.'),
		];
	}
}
