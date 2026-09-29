<x-hook-layout :hook='$deployment->hook'>
	<x-slot name="title">{{ $deployment->hook->name }} at {{ $deployment->created_at->isoFormat('LLLL') }}</x-slot>

	<x-slot name="header">{{ $deployment->hook->name }} at {{ $deployment->created_at->isoFormat('LLLL') }} </x-slot>

	<div class="flex">
		<h2 class="mb-2 text-lg">{{ ucfirst($deployment->state) }}
			@if ($deployment->exit_code !== null)
				<span class="text-sm font-normal text-gray-500">(exit code {{ $deployment->exit_code }})</span>
			@endif
			@if ($deployment->state == 'started' || $deployment->state == 'running')
				<x-button id="refreshButton" onclick="location.reload();">Refresh</x-button>

				<label class="ml-3 text-sm font-normal text-gray-600">
					<x-checkbox id="autoRefresh" class="mr-1 align-middle" />
					Auto refresh
				</label>
			@endif
		</h2>
		<div class="flex-1"></div>
		<div>{{ $deployment->created_at->diffForHumans() }}</div>
	</div>

	<div class="p-3 font-mono text-white whitespace-pre-wrap bg-black">{!! $deployment->manager()->getOutputHtml() !!}</div>

	<script>
		// Reload every few seconds while the deployment is still going, so the
		// output grows on its own.  The page reloads itself, which would wipe
		// an ordinary checkbox, so the choice is kept in localStorage.
		(function() {
			var key = "deployable.autoRefresh.deployment{{ $deployment->id }}";

			// No Refresh button means it isn't running any more.  Drop the flag
			// rather than leaving it to spring back on some later visit.
			if (!document.getElementById("refreshButton")) {
				localStorage.removeItem(key);
				return;
			}

			var box = document.getElementById("autoRefresh");
			var stored = localStorage.getItem(key);

			// On by default, until somebody ticks it off.
			box.checked = stored === null ? true : stored === "1";

			box.addEventListener("change", function() {
				localStorage.setItem(key, box.checked ? "1" : "0");
			});

			setInterval(function() {
				if (box.checked) {
					location.reload();
				}
			}, 3000);
		})();
	</script>

</x-hook-layout>
