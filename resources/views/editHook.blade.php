<x-app-layout>
    <x-slot name="title">Edit Hook</x-slot>

    <x-slot name="header">Edit Hook</x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <form method="post">
                        @csrf

                        <div class="mb-5">
                            <label>Name</label><br>
                            <x-input name="name" type="text" class="w-full" value="{{ old('name') }}" autofocus />
                        </div>

                        <div class="mb-5">
                            <label>Slug</label><br>
                            <x-input name="slug" type="text" class="w-full" value="{{ old('slug', $slug) }}"
                                onkeydown="return slugTest(event)" onkeyup="updateSlug(this)" /><br>
                            <div class="text-gray-400">The hook will be {{ $baseURL }}/<span
                                    id="slug">{{ old('slug', $slug) }}</div>
                        </div>


                        <x-button>Save</x-button>
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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
