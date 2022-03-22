<x-app-layout>
	<x-slot name="title">Deployment</x-slot>

	<x-slot name="header">Deployment: {{ $deployment->hook->name }} at {{ $deployment->created_at }} </x-slot>

	<div class="py-12">
		<div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
			<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
				<div class="p-6 bg-white border-b border-gray-200">

					<h2 class="text-lg">Output</h2>
					<div class="p-3 font-mono text-white whitespace-pre-wrap bg-black">{{ $deployment->manager()->getOutput() }}</div>

				</div>
			</div>
		</div>
	</div>
</x-app-layout>
