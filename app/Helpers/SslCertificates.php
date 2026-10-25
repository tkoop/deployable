<?php

namespace App\Helpers;

use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Asks Let's Encrypt for a certificate, and reads the answer back.
 *
 * The app runs as www-data with no privilege at all, and certbot needs root,
 * so the two sides talk through files: a *.req lands in /etc/deployable/ssl-requests,
 * deployable-ssl runs certbot, and a *.json appears in ssl-results. That's the
 * whole channel - no sudo, no root helper process, nothing to be talked into
 * running arbitrary commands.
 *
 * Every request carries a nonce that comes back with the answer, so a result
 * left over from an earlier attempt can never be mistaken for this one's.
 *
 * certbot takes a few seconds, which is a long time to hold a web request open
 * under php-fpm's 30-second limit, so the wait is short: if the answer hasn't
 * come back in time the site sits at "pending" and there's a button to check
 * again.
 */
class SslCertificates {

	/**
	 * Request a certificate for a site and wait a little while for it.
	 *
	 * @return array{success: bool, output: string, pending: bool}
	 */
	public static function request(Site $site): array {
		$blockers = Check::blockers(array_merge($site->preflight(), Preflight::ssl()));

		if ($blockers) {
			return [
				"success" => false,
				"pending" => false,
				"output" => "Nothing was requested, because:\n\n" .
					implode("\n\n", array_map(fn (Check $check) => $check->instructions(), $blockers)),
			];
		}

		$nonce = Str::random(24);

		$site->ssl_state = "pending";
		$site->ssl_nonce = $nonce;
		$site->ssl_output = null;
		$site->save();

		if (!self::deliver($site, $nonce)) {
			return [
				"success" => false,
				"pending" => false,
				"output" => "Couldn't write the request to " . config("deployable.ssl_request_dir") .
					".\n\n" . self::inboxFix(),
			];
		}

		return self::await($site, $nonce);
	}

	/**
	 * Look for the answer to the request the site is currently waiting on.
	 *
	 * @return array{success: bool, output: string, pending: bool}
	 */
	public static function status(Site $site): array {
		if ($site->ssl_nonce === null) {
			return [
				"success" => false,
				"pending" => false,
				"output" => "No certificate request is outstanding for {$site->domain}.",
			];
		}

		return self::await($site, $site->ssl_nonce, 0);
	}

	/**
	 * Withdraw a request that hasn't been answered, so unticking SSL doesn't
	 * leave certbot working on something nobody wants any more.
	 */
	public static function withdraw(Site $site): void {
		@unlink(config("deployable.ssl_request_dir") . "/{$site->domain}.req");

		$site->ssl_state = "none";
		$site->ssl_nonce = null;
		$site->ssl_output = null;
		$site->save();
	}

	// ---------------------------------------------------------------------

	/**
	 * Poll the results box for up to $seconds.
	 *
	 * @return array{success: bool, output: string, pending: bool}
	 */
	private static function await(Site $site, string $nonce, int $seconds = null): array {
		$deadline = microtime(true) + ($seconds ?? config("deployable.ssl_wait_seconds"));

		do {
			$result = self::result($site, $nonce);

			if ($result !== null) {
				return self::settle($site, $result);
			}

			usleep(400000);
		} while (microtime(true) < $deadline);

		return [
			"success" => true,
			"pending" => true,
			"output" => "certbot is still working on {$site->domain}. That's normal - the site keeps " .
				"serving over http meanwhile. Press \"Check the certificate\" in a moment to see the answer.",
		];
	}

	/**
	 * The answer to this request, and nothing else: a result with a different
	 * nonce is left over from an earlier attempt.
	 */
	private static function result(Site $site, string $nonce): array|null {
		$path = rtrim(config("deployable.ssl_result_dir"), "/") . "/{$site->domain}.json";

		if (!is_file($path)) {
			return null;
		}

		$data = json_decode((string) @file_get_contents($path), true);

		if (!is_array($data) || ($data["nonce"] ?? null) !== $nonce) {
			return null;
		}

		return $data;
	}

	/**
	 * Record what certbot said and, when it succeeded, put the https vhost in
	 * place to use the certificate it just wrote.
	 *
	 * @return array{success: bool, output: string, pending: bool}
	 */
	private static function settle(Site $site, array $result): array {
		$output = trim((string) ($result["output"] ?? ""));

		if (!($result["ok"] ?? false)) {
			$site->ssl_state = "failed";
			$site->ssl_output = $output;
			$site->save();

			return [
				"success" => false,
				"pending" => false,
				"output" => "Let's Encrypt refused the certificate for {$site->domain}:\n\n" .
					($output !== "" ? $output : "certbot gave no reason."),
			];
		}

		$site->ssl_state = "active";
		$site->ssl_output = $output;
		$site->save();

		// The certificate exists; now Apache needs the vhost that uses it.
		$written = $site->apache()->write();

		return [
			"success" => $written["success"],
			"pending" => false,
			"output" => "Certificate issued for {$site->domain}. https is on, and http redirects to it.\n\n" .
				$written["output"],
		];
	}

	/**
	 * Drop the request where deployable-ssl is watching for it.
	 */
	private static function deliver(Site $site, string $nonce): bool {
		$directory = config("deployable.ssl_request_dir");

		if (!is_dir($directory) || !is_writable($directory)) {
			return false;
		}

		$payload = json_encode([
			"domain" => $site->domain,
			"email" => $site->ssl_email ?: null,
			"webroot" => $site->document_root,
			"nonce" => $nonce,
			"requested_at" => gmdate("c"),
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		// A dotfile first, so nothing watching the directory can pick up a
		// half-written request.
		$temp = $directory . "/.tmp-" . bin2hex(random_bytes(6));

		if (@file_put_contents($temp, $payload . "\n") === false) {
			return false;
		}

		@chmod($temp, 0644);

		if (!@rename($temp, $directory . "/{$site->domain}.req")) {
			@unlink($temp);
			return false;
		}

		return true;
	}

	private static function inboxFix(): string {
		return "On the server, run:\n\n" .
			"sudo bash " . config("deployable.install_script") . "\n\n" .
			"Then check with:\n\n" .
			"ls -ld " . config("deployable.ssl_request_dir");
	}
}
