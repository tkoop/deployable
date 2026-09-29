<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
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

	/**
	 * The copy button reads the secret out of x-data. A Blade directive placed
	 * inside a component tag compiles to a literal attribute value instead, which
	 * silently copies the raw expression rather than the token, so pin it down.
	 */
	public function test_the_copy_button_carries_the_real_token(): void {
		$admin = $this->admin();

		$this->actingAs($admin)->post("/api-tokens", ["name" => "laptop"]);

		$plainText = session("newToken");

		$html = $this->actingAs($admin)->get("/api-tokens")->getContent();

		// Nothing left uncompiled.
		$this->assertStringNotContainsString("@js(", $html);

		// The secret reaches the browser as a JS string literal, not as markup.
		$this->assertStringContainsString("token: ".Js::from($plainText)->toHtml(), $html);

		$this->assertStringContainsString("navigator.clipboard.writeText(token)", $html);
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

	public function test_the_admin_can_rotate_a_token(): void {
		$admin = $this->admin();
		$id = $admin->createToken("laptop")->accessToken->id;

		$response = $this->actingAs($admin)->post("/api-tokens/{$id}/rotate");

		$response->assertRedirect("/api-tokens");

		$plainText = session("newToken");

		$this->assertNotNull($plainText);
		$this->assertStringContainsString("|", $plainText);

		// The replacement keeps the name, so the row is still recognisable.
		$this->actingAs($admin)
			->get("/api-tokens")
			->assertOk()
			->assertSee($plainText, escape: false)
			->assertSee("laptop");
	}

	/**
	 * Rotation is only a recovery path if the old secret actually dies.
	 */
	public function test_rotating_invalidates_the_old_token(): void {
		$admin = $this->admin();
		$old = $admin->createToken("laptop")->plainTextToken;
		$id = $admin->tokens()->first()->id;

		$this->actingAs($admin)->post("/api-tokens/{$id}/rotate");

		$this->app["auth"]->forgetGuards();

		$this->postJson("/mcp", [
			"jsonrpc" => "2.0",
			"id" => 1,
			"method" => "tools/list",
		], [
			"Accept" => "application/json, text/event-stream",
			"Authorization" => "Bearer {$old}",
		])->assertUnauthorized();

		// And the new one works.
		$this->app["auth"]->forgetGuards();

		$this->postJson("/mcp", [
			"jsonrpc" => "2.0",
			"id" => 1,
			"method" => "tools/list",
		], [
			"Accept" => "application/json, text/event-stream",
			"Authorization" => "Bearer ".session("newToken"),
		])->assertOk();
	}

	/**
	 * Rotating must not leave a second copy of the token behind.
	 */
	public function test_rotating_replaces_rather_than_adds(): void {
		$admin = $this->admin();
		$id = $admin->createToken("laptop")->accessToken->id;

		$this->actingAs($admin)->post("/api-tokens/{$id}/rotate");

		$this->assertDatabaseCount("personal_access_tokens", 1);
	}

	/**
	 * A rotation is a new secret for the same client, so a scoped-down token
	 * shouldn't silently regain full access by being rotated.
	 */
	public function test_rotating_preserves_abilities(): void {
		$admin = $this->admin();
		$id = $admin->tokens()->create([
			"name" => "readonly",
			"token" => hash("sha256", "irrelevant"),
			"abilities" => ["deploy:read"],
		])->id;

		$this->actingAs($admin)->post("/api-tokens/{$id}/rotate");

		$abilities = $admin->tokens()->first()->abilities;

		$this->assertSame(["deploy:read"], $abilities);
	}

	public function test_rotating_only_reaches_your_own_tokens(): void {
		$admin = $this->admin();
		$other = User::create(["name" => "Someone Else"]);
		$otherId = $other->createToken("theirs")->accessToken->id;

		$this->actingAs($admin)->post("/api-tokens/{$otherId}/rotate");

		$this->assertDatabaseHas("personal_access_tokens", ["name" => "theirs"]);
		$this->assertNull(session("newToken"));
	}

	public function test_rotating_an_unknown_token_is_handled(): void {
		$this->actingAs($this->admin())
			->post("/api-tokens/999/rotate")
			->assertRedirect("/api-tokens");

		$this->assertNull(session("newToken"));
	}

	public function test_the_dashboard_links_to_the_page(): void {
		$this->actingAs($this->admin())
			->get("/")
			->assertOk()
			->assertSee("API Tokens");
	}
}
