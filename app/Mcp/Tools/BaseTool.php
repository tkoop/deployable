<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\ToolException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Tools implement run() and can throw a ToolException; anything the caller
 * got wrong comes back as an MCP error rather than a 500.
 *
 * Response::structured() yields a ResponseFactory rather than a Response, so
 * both are accepted.
 */
abstract class BaseTool extends Tool {
	public function handle(Request $request): Response|ResponseFactory {
		try {
			return $this->run($request);
		} catch (ToolException $exception) {
			return Response::error($exception->getMessage());
		} catch (ValidationException $exception) {
			return Response::error(implode(" ", $exception->validator->errors()->all()));
		}
	}

	abstract protected function run(Request $request): Response|ResponseFactory;
}
