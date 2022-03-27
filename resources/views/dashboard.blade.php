<x-app-layout>
	<x-slot name="title">Dashboard</x-slot>

	<x-slot name="header">
		{{ __('Dashboard') }}
	</x-slot>

	<x-slot name="headerRight">
		<x-button onclick="window.location='/newHook'">New Hook</x-button>
	</x-slot>

	@if (session()->has('errors') || session()->has('status'))
		<div class="mx-auto mt-6 max-w-7xl sm:px-6 lg:px-8">
			<!-- Session Status -->
			<x-auth-session-status class="mb-4" :status="session('status')" />

			<!-- Validation Errors -->
			<x-auth-validation-errors class="mb-4" :status="session('errors')" />
		</div>
	@endif


	@if (count($hooks) == 0)
		<div class="my-6">
			<div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
				<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
					<div class="p-6 bg-white border-b border-gray-200">
						<p>You don't have any hooks yet. Click the button to make a new one.</p>
					</div>
				</div>
			</div>
		@else
			<div class="grid grid-cols-1 gap-6 mx-auto my-6 md:px-6 max-w-7xl md:grid-cols-2 lg:grid-cols-3">
				@foreach ($hooks as $hook)
					<div class="overflow-hidden bg-white shadow-sm hover:shadow-md sm:rounded-lg">
						<a href="/hook/{{ $hook->id }}/view">
							<div class="p-4 bg-white border-b border-gray-200 sm:p-6">
								<div>{{ $hook->name }}</div>
								<div class="text-gray-500">Last deployed: {{ $hook->lastDeployTime()?->diffForHumans() ?? 'never' }}</div>
							</div>
						</a>
					</div>
				@endforeach
			</div>
	@endif

</x-app-layout>
