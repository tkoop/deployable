<?php

namespace App\Helpers;

/**
 * What counts as a domain, and the validation rules that depend on it.
 *
 * Shared by the web form, the MCP tools and the server-side checks, because a
 * domain that's legal in one place and not the other is a site that half
 * works. The pattern is deliberately narrow: lowercase hostnames only, at
 * least two labels, no leading or trailing hyphen - which also means the name
 * is safe to use as a filename in /etc/apache2/deployable.
 */
class SiteValidation {
	public const DOMAIN_PATTERN = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/';

	public static function isDomain(string $domain): bool {
		return preg_match(self::DOMAIN_PATTERN, $domain) === 1;
	}

	/**
	 * @param int|null $exceptSiteId the site being updated, so its own domain
	 *                               doesn't collide with itself
	 * @return array<string, array<int, string>>
	 */
	public static function rules(int|null $exceptSiteId = null): array {
		$domain = ["required", "max:255", "regex:" . self::DOMAIN_PATTERN];

		// SQLite doesn't care, but a domain twice in the table would mean two
		// hooks claiming to serve the same name.
		$domain[] = $exceptSiteId === null
			? "unique:sites,domain"
			: "unique:sites,domain,{$exceptSiteId}";

		return [
			"domain" => $domain,
			"document_root" => ["required", "max:255", "starts_with:/"],
			"type" => ["required", "in:php,static"],
			"php_version" => ["required_if:type,php", "max:16"],
			"ssl_email" => ["nullable", "email", "max:255"],
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function messages(): array {
		return [
			"domain.regex" => "That isn't a domain name. Use lowercase letters, digits, dots and hyphens, " .
				"like example.com or bible.timkoop.com.",
			"domain.unique" => "Deployable already serves that domain for another hook. " .
				"Two hooks can't share one name.",
			"document_root.starts_with" => "The document root must be a full path, like /var/www/example/public.",
			"type.in" => "Type must be either PHP or static.",
			"php_version.required_if" => "Pick the PHP version this site should run on.",
			"ssl_email.email" => "Let's Encrypt needs a reachable email address for expiry warnings.",
		];
	}
}
