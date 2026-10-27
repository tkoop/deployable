<x-hook-layout :hook='$hook'>
	<x-slot name="title">{{ $hook->name }} - logs</x-slot>

	<x-slot name="header">{{ $hook->name }} - logs</x-slot>

	<div class="mb-6">
		<h3 class="mb-2 text-lg font-semibold text-gray-800">PHP / Apache error log</h3>
		<div class="mb-3 text-gray-500">
			<span class="font-mono">{{ $path }}</span>
			@if (!$exists)
				&mdash; doesn't exist yet.
			@endif
		</div>
		<div class="p-3 font-mono text-sm text-white whitespace-pre-wrap bg-black" style="min-height:200px; max-height:80vh; overflow:auto;">{{ $contents }}</div>
	</div>

	<div class="mb-6">
		<h3 class="mb-2 text-lg font-semibold text-gray-800">Application log</h3>
		<div class="mb-3 text-gray-500">
			<span class="font-mono">{{ $appPath }}</span>
			@if (!$appExists)
				&mdash; doesn't exist yet.
			@endif
		</div>
		<div class="p-3 font-mono text-sm text-white whitespace-pre-wrap bg-black" style="min-height:200px; max-height:80vh; overflow:auto;">{{ $appContents }}</div>
	</div>
</x-hook-layout>