<x-hook-layout :hook='$hook'>
	<x-slot name="title">{{ $hook->name }} - environment</x-slot>

	<x-slot name="header">{{ $hook->name }} - environment</x-slot>

	<form method="post" action="/hook/{{ $hook->id }}/env">
		@csrf

		<div class="mb-3 text-gray-500">
			<span class="font-mono">{{ $envFile->path() }}</span>
			@if (!$envFile->exists())
				&mdash; doesn't exist yet, saving will create it.
			@endif
		</div>

		@if (count($envFile->variableNames()) > 0)
			<div class="mb-3">
				<div class="mb-1">Variables</div>
				<div class="flex flex-wrap gap-1">
					@foreach ($envFile->variableNames() as $variable)
						<span class="px-2 py-1 font-mono text-xs bg-gray-200 rounded">{{ $variable }}</span>
					@endforeach
				</div>
			</div>
		@endif

		<div class="mb-5">
			<x-textarea name="env" style="min-height:400px" class="w-full font-mono" spellcheck="false"
				autocapitalize="off" autocomplete="off">{{ old('env', $envFile->get()) }}</x-textarea>
		</div>

		@if (session()->has('configOutput'))
			<div class="mb-5">
				<div class="mb-1">Config cache output</div>
				<div class="p-3 font-mono text-xs whitespace-pre-wrap bg-gray-100 rounded">{{ session('configOutput') }}</div>
			</div>
		@endif

		<div class="mb-5">
			<label class="flex items-start">
				<x-checkbox name="refreshConfig" value="1" class="mt-1 mr-2" />
				<span>
					Also clear and rebuild the project's config cache
					<div class="font-mono text-xs text-gray-400">php artisan config:clear &amp;&amp; php artisan config:cache</div>
				</span>
			</label>
		</div>

		<div class="flex justify-between">
			<x-button>Save</x-button>
		</div>
	</form>

</x-hook-layout>
