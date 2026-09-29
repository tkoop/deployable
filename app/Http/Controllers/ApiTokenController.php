<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller {
	/**
	 * Tokens for the signed-in admin, and the endpoint they're good for.
	 */
	public function index(): View {
		$user = request()->user();

		return view("apiTokens", [
			"tokens" => $user->tokens()->orderBy("id", "desc")->get(),
			"endpoint" => url("/mcp"),
		]);
	}

	public function store(Request $request): RedirectResponse {
		$request->validate([
			"name" => "required|string|max:255",
		]);

		// The plaintext token is shown exactly once, here. Sanctum keeps only a
		// hash, so there is no second chance to read it.
		$token = $request->user()->createToken($request->string("name")->toString());

		return redirect("/api-tokens")
			->with("newToken", $token->plainTextToken)
			->with("status", "Token created. Copy it now — it won't be shown again.");
	}

	public function destroy(Request $request, int $id): RedirectResponse {
		$token = $request->user()->tokens()->find($id);

		if ($token != null) {
			$token->delete();
		}

		return redirect("/api-tokens")->with("status", "Token revoked.");
	}

	/**
	 * Replace a token's secret, keeping its name, abilities and expiry.
	 *
	 * Only the hash is stored, so a lost token can't be read back — this is the
	 * way out. The old secret stops working immediately, which is the point:
	 * rotate when you suspect the old one is compromised, not just misplaced.
	 */
	public function rotate(Request $request, int $id): RedirectResponse {
		$token = $request->user()->tokens()->find($id);

		if ($token == null) {
			return redirect("/api-tokens")->with("status", "Token not found.");
		}

		$abilities = $token->abilities ?: ["*"];
		$expiresAt = $token->expires_at;
		$name = $token->name;

		$token->delete();

		$replacement = $request->user()->createToken($name, $abilities, $expiresAt);

		return redirect("/api-tokens")
			->with("newToken", $replacement->plainTextToken)
			->with("status", "Token rotated. Its old value no longer works.");
	}
}
