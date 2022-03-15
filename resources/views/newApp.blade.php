<x-app-layout>
    <x-slot name="title">New App</x-slot>

    <x-slot name="header">New App</x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <form method="post">

                        <div class="mb-5">
                            <label>Name</label><br>
                            <x-input name="name" type="text" class="w-full" autofocus />
                        </div>

                        <div class="mb-5">
                            <label>Path location (local file system)</label><br>
                            <x-input name="path" type="text" class="w-full" value="{{ $path }}" />
                        </div>

                        <div class="mb-5">
                            <label>Git location URL (ssh or https)</label><br>
                            <x-input name="git" type="text" class="w-full" />
                        </div>

                        <div class="mb-5">
                            <label>Git branch</label><br>
                            <x-input name="branch" type="text" class="w-full" value="master" />
                        </div>

                        <x-button>Save</x-button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
