<?php

namespace Tests\Feature;

use App\Models\Hook;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase {
	use RefreshDatabase;

	public function test_a_site_exposes_both_vhost_paths(): void {
		$hook = Hook::create([
			"name" => "Example",
			"slug" => "example",
			"script" => "echo hi",
			"directory" => "/var/www/example",
		]);

		$site = Site::create([
			"hook_id" => $hook->id,
			"domain" => "example.com",
			"document_root" => "/var/www/example/public",
			"type" => "php",
			"php_version" => "8.3",
			"ssl_state" => "none",
		]);

		// Both paths must exist: the page renders a checklist that calls
		// $site->sslVhostPath() and $site->vhostPath(), and a missing
		// method here is a 500 on /hook/{id}/site.
		$this->assertStringEndsWith("example.com.conf", $site->vhostPath());
		$this->assertStringEndsWith("example.com-le-ssl.conf", $site->sslVhostPath());
	}

	public function test_all_hosts_includes_aliases(): void {
		$hook = Hook::create([
			"name" => "Example",
			"slug" => "example",
			"script" => "echo hi",
			"directory" => "/var/www/example",
		]);

		$site = Site::create([
			"hook_id" => $hook->id,
			"domain" => "example.com",
			"aliases" => ["www.example.com", "blog.example.com"],
			"document_root" => "/var/www/example/public",
			"type" => "php",
			"php_version" => "8.3",
			"ssl_state" => "none",
		]);

		$this->assertSame(
			["example.com", "www.example.com", "blog.example.com"],
			$site->allHosts()
		);
	}

	public function test_aliases_are_deduplicated_and_validated(): void {
		$this->assertSame(
			["a.example.com", "b.example.com"],
			\App\Helpers\SiteValidation::normalizeAliases("a.example.com\na.example.com, b.example.com\nnot-a-domain")
		);
	}

	public function test_the_site_page_renders(): void {
		$hook = Hook::create([
			"name" => "Example",
			"slug" => "example",
			"script" => "echo hi",
			"directory" => "/var/www/example",
		]);

		$this->actingAs(\App\Models\User::create(["name" => "Admin"]))
			->get("/hook/{$hook->id}/site")
			->assertOk();
	}
}