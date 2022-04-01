<x-hook-layout :hook='$hook'>

	<x-slot name="title">{{ $hook->name }} - deployments</x-slot>

	<x-slot name="header" class="mb-10">{{ $hook->name }} - deployments</x-slot>

	@if ($deployments->count() == 0)
		No deployments yet.
	@endif

	@foreach ($deployments as $deployment)
		<div class="flex">
			<h2 class="mb-2 text-lg">
				{{ $deployment->created_at->isoFormat('LLLL') }}
			   - {{ ucfirst($deployment->state) }}
			</h2>
			<div class="flex-1"></div>
			<div>{{ $deployment->created_at->diffForHumans() }}</div>
		</div>


		<div class="h-40 p-3 mb-3 overflow-auto font-mono text-white whitespace-pre-wrap bg-black">
			{{ $deployment->manager()->getOutput() }}</div>
	@endforeach


</x-hook-layout>
