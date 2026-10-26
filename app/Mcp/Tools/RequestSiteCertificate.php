<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\ToolException;
use App\Mcp\Support\Sites;
use App\Helpers\SslCertificates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Ask Let\'s Encrypt for a certificate for a site. The site must have an enabled vhost in the drop-in directory and a valid email. Takes a few seconds; if certbot is still working, ssl_state becomes "pending" and you can poll with check_site_certificate.')]
class RequestSiteCertificate extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);
		$site = Sites::require($hook);

		$result = SslCertificates::request($site);

		return Response::structured([
			"success" => $result["success"],
			"pending" => $result["pending"],
			"site" => Sites::detail($site->fresh()),
			"output" => $result["output"],
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()->description('The hook whose site to certify. Required.'),
			"slug" => $schema->string()->description('Hook slug as an alternative to hook_id.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"success" => $schema->boolean()->required(),
			"pending" => $schema->boolean()->required(),
			"site" => $schema->object()->required(),
			"output" => $schema->string()->required(),
		];
	}
}

#[Description('Check whether a pending certificate request has been answered. Updates ssl_state and, on success, writes the https vhost.')]
class CheckSiteCertificate extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);
		$site = Sites::require($hook);

		$result = SslCertificates::status($site);

		return Response::structured([
			"success" => $result["success"],
			"pending" => $result["pending"],
			"site" => Sites::detail($site->fresh()),
			"output" => $result["output"],
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()->description('The hook whose site to check. Required.'),
			"slug" => $schema->string()->description('Hook slug as an alternative to hook_id.'),
		];
	}

	public function outputSchema(JsonSchema $schema): array {
		return [
			"success" => $schema->boolean()->required(),
			"pending" => $schema->boolean()->required(),
			"site" => $schema->object()->required(),
			"output" => $schema->string()->required(),
		];
	}
}

#[Description('Turn HTTPS off for a site: drop the https vhost and the http-to-https redirect. The certificate itself is left on disk for certbot to keep renewing.')]
class RemoveSiteCertificate extends BaseTool {
	protected function run(Request $request): Response|ResponseFactory {
		$hook = Sites::resolveHook($request);
		$site = Sites::require($hook);

		\App\Helpers\SslCertificates::withdraw($site);

		$write = $site->apache()->write();

		return Response::structured([
			"success" => $write["success"],
			"site" => Sites::detail($site->fresh()),
			"output" => "HTTPS is off for {$site->domain}. The certificate itself was left on disk.\n\n" . $write["output"],
		]);
	}

	public function schema(JsonSchema $schema): array {
		return [
			"hook_id" => $schema->integer()->description('The hook whose site to decertify. Required.'),
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
