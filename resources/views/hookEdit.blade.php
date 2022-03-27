<x-hook-layout :hook='$hook'>
	<x-slot name="title">{{ $hook->name }} - Edit</x-slot>

	<x-slot name="header">{{ $hook->name }} - Edit</x-slot>


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
			<label>Script</label><br>
			<x-textarea name="script" type="text" style="min-height:200px" class="w-full">
				{{ old('slug', $hook->script) }}</x-textarea>
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
