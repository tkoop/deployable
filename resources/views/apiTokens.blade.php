<x-app-layout>
	<x-slot name="title">API Tokens</x-slot>

	<x-slot name="header">
		{{ __('API Tokens') }}
	</x-slot>

	@if (session()->has('errors') || session()->has('status') || session()->has('newToken'))
		<div class="mx-auto mt-6 max-w-7xl sm:px-6 lg:px-8">
			<!-- Session Status -->
			<x-auth-session-status class="mb-4" :status="session('status')" />

			<!-- Validation Errors -->
			<x-auth-validation-errors class="mb-4" :status="session('errors')" />
		</div>
	@endif

	<div class="mx-auto my-6 max-w-7xl sm:px-6 lg:px-8">

		@if (session()->has('newToken'))
			<div class="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg" x-data="{ copied: false, token: @js(session('newToken')) }">
				<div class="p-6 bg-white border-b border-gray-200">
					<h3 class="mb-2 text-lg font-medium text-gray-900">Your new token</h3>
					<p class="mb-3 text-sm text-gray-600">
						Copy this now. Only a hash is stored, so it can't be shown again — if you lose it, use
						<strong>Rotate</strong> on the token below to mint a replacement.
					</p>
					<div class="flex items-start gap-2">
						<div class="grow p-3 font-mono text-sm break-all bg-gray-100 rounded">
							{{ session('newToken') }}
						</div>
						<x-button type="button" class="shrink-0"
							@click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 2000)"
							x-text="copied ? 'Copied' : 'Copy'">Copy</x-button>
					</div>
				</div>
			</div>
		@endif

		<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
			<div class="p-6 bg-white border-b border-gray-200">
				<h3 class="mb-2 text-lg font-medium text-gray-900">MCP endpoint</h3>
				<p class="mb-3 text-sm text-gray-600">
					Point an MCP client here and send the token as
					<code class="px-1 bg-gray-100 rounded">Authorization: Bearer &lt;token&gt;</code>.
					This server can read your hooks, trigger deployments, and edit deploy scripts.
				</p>
				<div class="p-3 font-mono text-sm break-all bg-gray-100 rounded">{{ $endpoint }}</div>
				<p class="mt-3 text-sm text-gray-600">
					A token is as powerful as you are: anyone holding one can run the bash in your deploy scripts on this
					server. Treat it like a password, and revoke anything you no longer need.
				</p>
			</div>
		</div>

		<div class="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
			<div class="p-6 bg-white border-b border-gray-200">
				<h3 class="mb-4 text-lg font-medium text-gray-900">Create a token</h3>
				<form method="post" action="/api-tokens" class="flex items-end">
					@csrf
					<div class="grow">
						<x-label for="name" value="Name" />
						<x-input id="name" class="block w-full mt-1" type="text" name="name" required autofocus
							placeholder="laptop, ci, opencode" />
					</div>
					<div class="ml-3">
						<x-button>Create Token</x-button>
					</div>
				</form>
			</div>

			<div class="p-6 bg-white border-b border-gray-200">
				<h3 class="mb-4 text-lg font-medium text-gray-900">Existing tokens</h3>

				@if (count($tokens) == 0)
					<p class="text-sm text-gray-600">No tokens yet.</p>
				@else
					<table class="w-full text-sm text-left">
						<thead>
							<tr class="text-gray-500">
								<th class="py-2 pr-4 font-medium">Name</th>
								<th class="py-2 pr-4 font-medium">Created</th>
								<th class="py-2 pr-4 font-medium">Last used</th>
								<th class="py-2 font-medium"></th>
							</tr>
						</thead>
						<tbody>
							@foreach ($tokens as $token)
								<tr class="border-t border-gray-200">
									<td class="py-2 pr-4">{{ $token->name }}</td>
									<td class="py-2 pr-4">{{ $token->created_at->diffForHumans() }}</td>
									<td class="py-2 pr-4">{{ $token->last_used_at?->diffForHumans() ?? 'never' }}</td>
									<td class="py-2">
										<div class="flex items-center gap-2">
											<form method="post" action="/api-tokens/{{ $token->id }}/rotate"
												onsubmit="return confirm('Rotate &quot;{{ $token->name }}&quot;? The current token stops working immediately.')">
												@csrf
												<x-button class="text-xs" title="Issue a new token value, invalidating the old one">Rotate</x-button>
											</form>
											<form method="post" action="/api-tokens/{{ $token->id }}/revoke"
												onsubmit="return confirm('Revoke &quot;{{ $token->name }}&quot;? This cannot be undone.')">
												@csrf
												<x-button class="text-xs" title="Delete this token for good">Revoke</x-button>
											</form>
										</div>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				@endif
			</div>
		</div>
	</div>

</x-app-layout>
