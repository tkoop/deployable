<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('No password') }}
        </h2>
    </x-slot>

    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <p class="mb-2"><img src="full_logo.png" style="height:55px"></p>

                <p class="mb-2">You need to specify a password in your .env file.</p>

                <p class="mb-2">You could copy the .env.example file to .env and change the value of
                    ADMIN_PASSWORD.</p>

                <p class="mb-2">
                    <x-button onclick="window.location = '/'">Reload</x-button>
                </p>
            </div>
        </div>
    </div>
    
</x-guest-layout>
