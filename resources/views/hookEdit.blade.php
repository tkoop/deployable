<x-hook-layout :hook='$hook'>
	<x-slot name="title">{{ $hook->name }} - edit</x-slot>

	<x-slot name="header">{{ $hook->name }} - edit</x-slot>


	<form method="post">
		@csrf

		<div class="mb-5">
			<label>Name</label><br>
			<x-input name="name" type="text" class="w-full" value="{{ old('name', $hook->name) }}" />
		</div>

		<div class="mb-5">
			<label>Slug</label><br>
			<x-input name="slug" type="text" class="w-full" value="{{ old('slug', $hook->slug) }}"
				onkeydown="return slugTest(event)" onkeyup="updateSlug(this)" /><br>
			<div class="text-gray-400">The hook is {{ $baseURL }}/<span id="slug">{{ old('slug', $hook->slug) }}
			</div>
		</div>

		<div class="mb-5">
			<label>Project Directory</label><br>
			<x-input name="directory" type="text" class="w-full font-mono" value="{{ old('directory', $hook->directory) }}"
				placeholder="/var/www/example" /><br>
			@if ($hook->envFile()->directoryExists())
				<div class="text-gray-400">Found on this server.  Edit its .env in the Environment tab.</div>
			@else
				<div class="text-red-500">This directory doesn't exist yet, so the .env can't be edited until it does.</div>
			@endif
		</div>

		<div class="mb-5">
			<label>Script</label><br>
			<x-textarea name="script" type="text" style="min-height:200px" class="w-full">
				{{ old('script', $hook->script) }}</x-textarea>
		</div>

		<div class="flex justify-between">
			<x-button>Save</x-button>
			<x-button-light name="delete" value="delete">Delete</x-button-light>
		</div>
	</form>

	<script>
	 function updateSlug(element) {
	  document.querySelector("#slug").innerHTML = element.value
	 }

	 function slugTest(event) {
	  var regex = /^[A-Za-z0-9]+$/
	  return regex.test(event.key)
	 }
	</script>

</x-hook-layout>
