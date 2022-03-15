<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        {{ __('Dashboard') }}
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <div>
                        <div style="float:right">
                            <x-button onclick="window.location='/newApp'">New App</x-button>
                        </div>

                        @if (count($apps) == 0)
                            <p>You don't have any apps yet. Click the button to make a new one.</p>
                        @endif

                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
