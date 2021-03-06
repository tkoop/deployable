<x-hook-layout :hook='$deployment->hook'>
	<x-slot name="title">{{ $deployment->hook->name }} at {{ $deployment->created_at->isoFormat('LLLL') }}</x-slot>

	<x-slot name="header">{{ $deployment->hook->name }} at {{ $deployment->created_at->isoFormat('LLLL') }} </x-slot>

	<div class="flex">
		<h2 class="mb-2 text-lg">{{ ucfirst($deployment->state) }}
			@if ($deployment->state == 'started' || $deployment->state == 'running')
				<x-button onclick="location.reload();">Refresh</x-button>
			@endif
		</h2>
		<div class="flex-1"></div>
		<div>{{ $deployment->created_at->diffForHumans() }}</div>
	</div>

	<div class="p-3 font-mono text-white whitespace-pre-wrap bg-black">{{ $deployment->manager()->getOutput() }}</div>

</x-hook-layout>
