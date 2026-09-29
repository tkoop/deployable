<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * This app has one login, and it is not the Breeze one: a single shared
 * ADMIN_PASSWORD from the environment, with no email, no per-user password and
 * no registration. These tests cover what actually happens.
 *
 * The password is pinned in phpunit.xml so the suite doesn't depend on the
 * developer's .env.
 */
class AuthenticationTest extends TestCase {
	use RefreshDatabase;

	protected string $password = "testing-password";

	protected function login(array $overrides = []) {
		return $this->post("/login", array_merge(["password" => $this->password], $overrides));
	}

	public function test_the_login_screen_can_be_rendered(): void {
		$this->get("/login")->assertOk();
	}

	public function test_the_right_password_authenticates_you(): void {
		User::create(["name" => "Admin"]);

		$this->login()->assertRedirect("/");

		$this->assertAuthenticated();
	}

	public function test_the_wrong_password_does_not(): void {
		User::create(["name" => "Admin"]);

		$this->login(["password" => "wrong-password"])->assertSessionHasErrors();

		$this->assertGuest();
	}

	/**
	 * A blank submission is a wrong password, not a way in.
	 */
	public function test_a_missing_password_does_not_authenticate_you(): void {
		User::create(["name" => "Admin"]);

		$this->post("/login")->assertSessionHasErrors();

		$this->assertGuest();
	}

	/**
	 * Nobody can sign up. The admin is created by the first login, below.
	 */
	public function test_the_first_login_creates_the_admin_user(): void {
		$this->assertDatabaseCount("users", 0);

		$this->login();

		$this->assertAuthenticated();
		$this->assertDatabaseCount("users", 1);
		$this->assertDatabaseHas("users", ["name" => "Admin"]);
	}

	/**
	 * Logins after the first must not stack up a second admin.
	 */
	public function test_later_logins_reuse_the_existing_admin(): void {
		$this->login();
		$this->post("/logout");

		$this->login();

		$this->assertAuthenticated();
		$this->assertDatabaseCount("users", 1);
	}

	public function test_logging_out_ends_the_session(): void {
		User::create(["name" => "Admin"]);

		$this->login();
		$this->assertAuthenticated();

		$this->post("/logout")->assertRedirect("/login");

		$this->assertGuest();
	}

	/**
	 * The dashboard is the app itself, so it is the thing worth protecting.
	 */
	public function test_the_dashboard_is_closed_to_guests(): void {
		$this->get("/")->assertRedirect("/login");
	}

	public function test_the_dashboard_opens_once_logged_in(): void {
		User::create(["name" => "Admin"]);

		$this->login();

		$this->get("/")->assertOk();
	}

	/**
	 * Email, per-user passwords, registration and password resets were all
	 * dropped when login became one shared secret. These routes are gone, and
	 * shouldn't quietly come back.
	 */
	public function test_the_removed_auth_routes_stay_removed(): void {
		foreach (["/register", "/forgot-password", "/reset-password", "/confirm-password", "/email/verify"] as $uri) {
			$this->get($uri)->assertNotFound();
		}
	}
}
