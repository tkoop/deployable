<?php

namespace App\Mcp\Resources;

use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Deployments;
use App\Models\Deployment;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('A deployment\'s raw console output as plain text, with ANSI colour codes left intact. Capped, so use the get_deployment_output tool when you need the tail of a long log.')]
#[MimeType('text/plain')]
class DeploymentOutputResource extends Resource implements HasUriTemplate {
	public function uriTemplate(): UriTemplate {
		return new UriTemplate('deployable://deployments/{deploymentId}/output');
	}

	public function handle(Request $request): Response {
		$id = $request->get("deploymentId");

		if (!ctype_digit((string) $id)) {
			throw new ToolException("'{$id}' isn't a deployment id.");
		}

		$deployment = Deployment::find((int) $id);

		if ($deployment == null) {
			throw new ToolException("There's no deployment with id {$id}.");
		}

		$output = Deployments::readOutput($deployment);

		if (!$output["exists"]) {
			throw new ToolException(
				"Deployment {$id} hasn't written any output yet. Its state is '{$deployment->state}'."
			);
		}

		return Response::text($output["content"]);
	}
}
