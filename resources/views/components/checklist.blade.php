@props(['checks' => [], 'title' => 'Checklist'])

@php
	$failing = array_values(array_filter($checks, fn ($check) => !$check->ok));
	$blockers = \App\Helpers\Check::blockers($checks);
@endphp

<div {{ $attributes->merge(['class' => 'mb-5']) }}>
	<div class="flex items-baseline justify-between mb-2">
		<div class="font-medium">{{ $title }}</div>

		<div class="text-xs">
			@if (count($failing) === 0)
				<span class="text-green-700">All {{ count($checks) }} checks passing</span>
			@elseif (count($blockers) > 0)
				<span class="text-red-700">{{ count($blockers) }} blocking, {{ count($failing) - count($blockers) }} to note</span>
			@else
				<span class="text-amber-700">{{ count($failing) }} to note, nothing blocking</span>
			@endif
		</div>
	</div>

	@if (count($failing) === 0)
		<ul class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
			@foreach ($checks as $check)
				<li class="text-sm text-gray-700"><span class="text-green-600">&#10003;</span> {{ $check->label }}</li>
			@endforeach
		</ul>
	@else
		<ul>
			@foreach ($checks as $check)
				@if ($check->ok)
					<li class="text-sm text-gray-500"><span class="text-green-600">&#10003;</span> {{ $check->label }}</li>
				@else
					<li class="my-3 pl-3 {{ $check->blocking ? 'border-l-4 border-red-400' : 'border-l-4 border-amber-400' }}">
						<div class="font-medium {{ $check->blocking ? 'text-red-800' : 'text-amber-800' }}">
							<span class="mr-1">&#10007;</span>{{ $check->label }}
							@if (!$check->blocking)
								<span class="ml-2 text-xs font-normal text-amber-700">won't stop the site</span>
							@endif
						</div>

						<div class="text-sm mt-1 whitespace-pre-line">{{ $check->problem }}</div>

						@if ($check->how !== '')
							<div class="text-sm mt-2 text-gray-600">{{ $check->how }}</div>
						@endif

						@if (count($check->commands) > 0)
							<pre class="mt-2 p-2 bg-gray-900 text-gray-100 text-xs rounded overflow-x-auto select-all">{{ implode("\n", $check->commands) }}</pre>
						@endif

						@if ($check->detail !== '')
							<pre class="mt-2 p-2 bg-gray-100 text-gray-700 text-xs rounded overflow-x-auto whitespace-pre-wrap">{{ $check->detail }}</pre>
						@endif

						@if ($check->verify !== '')
							<div class="mt-2 text-xs text-gray-600">
								Check it with:
								<code class="px-1 py-0.5 bg-gray-100 rounded select-all">{{ $check->verify }}</code>
							</div>
						@endif
					</li>
				@endif
			@endforeach
		</ul>
	@endif
</div>
