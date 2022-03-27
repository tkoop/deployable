<x-hook-layout :hook='$hook'>

	<x-slot name="title">Deployments</x-slot>

	<x-slot name="header" class="mb-10">Deployments</x-slot>

	@if ($deployments->count() == 0)
		No deployments yet.
	@endif

	@foreach ($deployments as $deployment)
		<h2 class="text-lg">{{ $deployment->created_at->toDayDateTimeString() }}</h2>

		<div class="h-40 p-3 mb-3 overflow-auto font-mono text-white whitespace-pre-wrap bg-black">{{ $deployment->manager()->getOutput() }}</div>
		
	@endforeach


</x-hook-layout>
