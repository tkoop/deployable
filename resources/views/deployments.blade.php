<x-app-layout>
	<x-slot name="title">Deployments</x-slot>

	<x-slot name="header" class="mb-10">Deployments</x-slot>

	@if ($deployments->count() == 0)
		<div class="py-12">
			<div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
				<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
					<div class="p-6 bg-white border-b border-gray-200">
						No deployments yet.
					</div>
				</div>
			</div>
		</div>
	@endif

	<div class="pt-5"></div>

	@foreach ($deployments as $deployment)
		<div class="py-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
			<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
				<div class="p-6 bg-white border-b border-gray-200">

					<h2 class="text-lg">{{ $deployment->created_at->toDayDateTimeString() }}</h2>

					<div class="h-40 p-3 overflow-auto font-mono text-white whitespace-pre-wrap bg-black">{{ $deployment->manager()->getOutput() }}</div>

				</div>
			</div>
		</div>
	@endforeach


</x-app-layout>
