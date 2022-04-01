<x-app-layout>
	<x-slot name="title">{{ $title }}</x-slot>

	<x-slot name="header">{{ $header }}</x-slot>

	<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>

	<div class="my-6">
		<div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

			@if (session()->has('errors') || session()->has('status'))
				<div>
					<!-- Session Status -->
					<x-auth-session-status class="mb-4" :status="session('status')" />

					<!-- Validation Errors -->
					<x-auth-validation-errors class="mb-4" :status="session('errors')" />
				</div>
			@endif

			<div class="flex">
				<div class="flex flex-col pr-2">

					<x-button-link href="/hook/{{ $hook->id }}/edit" class="w-full mb-2">Edit</x-button-link>

					<div class="w-full mb-2">
						<form method="post" action="/hook/{{ $hook->id }}/deploy">
							@csrf
							<x-button class="w-full">Deploy Now</x-button>
						</form>
					</div>

					@if ($hook->deployments()->count() > 0)
						<div>Deployments</div>

						<x-button-link href="/deployment/{{ $hook->deployments()->latest()->first()->id }}" class="w-full mb-2">
							Latest
						</x-button-link>

						<x-button-link href="/hook/{{ $hook->id }}/deployments" class="w-full mb-2">All</x-button-link>
					@endif
				</div>

				<div class="flex-grow overflow-hidden bg-white shadow-sm sm:rounded-lg">
					<div class="p-3 bg-white border-b border-gray-200">
						{{ $slot }}
					</div>
				</div>
			</div>
		</div>
	</div>
</x-app-layout>
