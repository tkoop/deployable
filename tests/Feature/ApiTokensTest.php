<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokensTest extends TestCase {
	use RefreshDatabase;

	protected function admin(): User {
		return User::create(["name" => "Admin"]);
	}

	public function test_guests_are_sent_to_login(): void {
		$this->get("/api-tokens")->assertRedirect("/login");
	}

	public function test_the_admin_can_view_the_page(): void {
		$this->actingAs($this->admin())
			->get("/api-tokens")
			->assertOk()
			->assertSee("API Tokens")
			->assertSee("/mcp");
	}

	public function test_the_admin_can_create_a_token(): void {
		$response = $this->actingAs($this->admin())
			->post("/api-tokens", ["name" => "laptop"]);

		$response->assertRedirect("/api-tokens");

		$this->assertDatabaseHas("personal_access_tokens", ["name" => "laptop"]);
	}

	/**
	 * Sanctum keeps only a hash, so the plaintext is shown exactly once.
	 */
	public function test_the_plaintext_token_is_shown_once_and_never_again(): void {
		$admin = $this->admin();

		$this->actingAs($admin)->post("/api-tokens", ["name" => "laptop"]);

		$plainText = session("newToken");

		$this->assertNotNull($plainText);
		$this->assertStringContainsString("|", $plainText);

		// The first page load is the one and only time it is shown.
		$this->actingAs($admin)
			->get("/api-tokens")
			->assertOk()
			->assertSee($plainText, escape: false);

		$this->actingAs($admin)
			->get("/api-tokens")
			->assertOk()
			->assertDontSee($plainText, escape: false);
	}

	public function test_the_created_token_authenticates_against_the_mcp_endpoint(): void {
		$admin = $this->admin();

		$this->actingAs($admin)->post("/api-tokens", ["name" => "laptop"]);

		$plainText = session("newToken");

		// Otherwise the session guard would authorise the request below and the
		// token would never be exercised.
		$this->app["auth"]->forgetGuards();

		$this->postJson("/mcp", [
			"jsonrpc" => "2.0",
			"id" => 1,
			"method" => "tools/list",
		], [
			"Accept" => "application/json, text/event-stream",
			"Authorization" => "Bearer {$plainText}",
		])->assertOk();

		// And the same request without it is refused.
		$this->app["auth"]->forgetGuards();

		$this->postJson("/mcp", [
			"jsonrpc" => "2.0",
			"id" => 1,
			"method" => "tools/list",
		], [
			"Accept" => "application/json, text/event-stream",
		])->assertUnauthorized();
	}

	public function test_a_token_needs_a_name(): void {
		$this->actingAs($this->admin())
			->post("/api-tokens", ["name" => ""])
			->assertSessionHasErrors("name");

		$this->assertDatabaseCount("personal_access_tokens", 0);
	}

	public function test_the_admin_can_revoke_a_token(): void {
		$admin = $this->admin();
		$id = $admin->createToken("laptop")->accessToken->id;

		$this->actingAs($admin)->post("/api-tokens/{$id}/revoke");

		$this->assertDatabaseCount("personal_access_tokens", 0);
	}

	public function test_a_revoked_token_stops_working(): void {
		$admin = $this->admin();
		$plainText = $admin->createToken("laptop")->plainTextToken;
		$id = $admin->tokens()->first()->id;

		$this->actingAs($admin)->post("/api-tokens/{$id}/revoke");

		// Drop the acting-as guard, or the request below would authenticate
		// through the session and never look at the revoked token.
		$this->app["auth"]->forgetGuards();

		$this->postJson("/mcp", [
			"jsonrpc" => "2.0",
			"id" => 1,
			"method" => "tools/list",
		], [
			"Accept" => "application/json, text/event-stream",
			"Authorization" => "Bearer {$plainText}",
		])->assertUnauthorized();
	}

	/**
	 * Tokens belong to a user, so one admin must not be able to revoke
	 * somebody else's by guessing an id.
	 */
	public function test_revoking_only_reaches_your_own_tokens(): void {
		$admin = $this->admin();
		$other = User::create(["name" => "Someone Else"]);
		$otherId = $other->createToken("theirs")->accessToken->id;

		$this->actingAs($admin)->post("/api-tokens/{$otherId}/revoke");

		$this->assertDatabaseHas("personal_access_tokens", ["name" => "theirs"]);
	}

	public function test_the_dashboard_links_to_the_page(): void {
		$this->actingAs($this->admin())
			->get("/")
			->assertOk()
			->assertSee("API Tokens");
	}
}
