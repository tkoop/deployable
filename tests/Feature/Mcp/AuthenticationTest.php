<?php

namespace Tests\Feature\Mcp;

use App\Models\Hook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase {
	use RefreshDatabase;

	protected function payload(): array {
		return [
			"jsonrpc" => "2.0",
			"id" => 1,
			"method" => "tools/list",
		];
	}

	protected function headers(?string $token): array {
		$headers = [
			"Content-Type" => "application/json",
			"Accept" => "application/json, text/event-stream",
		];

		if ($token !== null) {
			$headers["Authorization"] = "Bearer {$token}";
		}

		return $headers;
	}

	public function test_it_rejects_requests_with_no_token(): void {
		$this->postJson("/mcp", [], $this->headers(null))
			->assertUnauthorized();
	}

	public function test_it_rejects_a_bogus_token(): void {
		$user = User::create(["name" => "Admin"]);

		$this->postJson("/mcp", [], $this->headers("1|" . str_repeat("a", 60)))
			->assertUnauthorized();

		$this->assertDatabaseCount("personal_access_tokens", 0);
	}

	public function test_it_accepts_a_valid_token(): void {
		$user = User::create(["name" => "Admin"]);
		$token = $user->createToken("test")->plainTextToken;
		Hook::create(["name" => "Example", "slug" => "example", "script" => "echo hi"]);

		$response = $this->postJson("/mcp", $this->payload(), $this->headers($token));

		$response->assertOk();

		$this->assertStringContainsString("list-hooks", $response->getContent());
	}

	public function test_the_endpoint_only_accepts_post(): void {
		$user = User::create(["name" => "Admin"]);
		$token = $user->createToken("test")->plainTextToken;

		$this->get("/mcp", $this->headers($token))->assertStatus(405);
		$this->delete("/mcp", [], $this->headers($token))->assertStatus(405);
	}

/**
	 * routes/web.php returns early when ADMIN_PASSWORD is empty, taking every
	 * other route with it. The MCP endpoint has to be immune to that, which it
	 * is because Laravel\Mcp loads routes/ai.php from its own service provider
	 * rather than from RouteServiceProvider.
	 *
	 * The live setup-mode behaviour is deliberately not exercised by rebooting
	 * the app here: refreshApplication() and RefreshDatabase don't mix, and the
	 * reboot leaks into later tests. Asserting the mechanism instead is stable.
	 */
public function test_the_endpoint_is_not_dependent_on_the_web_route_file(): void {
		$this->assertFileExists(base_path("routes/ai.php"));

		$route = collect(app("router")->getRoutes()->getRoutes())
			->first(fn ($route) => $route->uri() === "mcp" && in_array("POST", $route->methods(), strict: true));

		$this->assertNotNull($route, "The POST /mcp route should be registered.");

		// Not in the web group means no session and no CSRF, and no dependency
		// on whatever routes/web.php decides to register.
		$this->assertNotContains("web", $route->gatherMiddleware());
		$this->assertContains("auth:sanctum", $route->gatherMiddleware());
	}
}
