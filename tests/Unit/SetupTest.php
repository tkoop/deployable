<?php

namespace Tests\Unit;

use App\Http\Controllers\SetupController;
use Tests\TestCase;

/**
 * An empty ADMIN_PASSWORD puts the whole app into setup mode: routes/web.php
 * registers nothing but /setup and a fallback, so the app is unreachable until
 * one is set. That's a lockout, not a convenience, and it was untested.
 *
 * The password is read through env(), so it is written to every adapter the
 * process might answer from. Writing to the repository alone is not enough here.
 */
class SetupTest extends TestCase {
	protected string $original;

	protected function setUp(): void {
		parent::setUp();

		$this->original = (string) env("ADMIN_PASSWORD", "");
	}

	protected function tearDown(): void {
		$this->setAdminPassword($this->original);

		parent::tearDown();
	}

	protected function setAdminPassword(string $value): void {
		putenv("ADMIN_PASSWORD=".$value);
		$_ENV["ADMIN_PASSWORD"] = $value;
		$_SERVER["ADMIN_PASSWORD"] = $value;
	}

	public function test_a_password_is_needed(): void {
		$this->setAdminPassword("");

		$this->assertTrue(SetupController::needsSetup());
	}

	public function test_a_set_password_means_setup_is_done(): void {
		$this->setAdminPassword("something");

		$this->assertFalse(SetupController::needsSetup());
	}
}
