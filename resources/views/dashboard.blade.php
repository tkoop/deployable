<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        {{ __('Dashboard') }}
    </x-slot>

    @if (session()->has('errors') || session()->has('status'))
        <div class="my-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 font-bold bg-white border-b border-gray-200">
                        <!-- Session Status -->
                        <x-auth-session-status class="mb-4" :status="session('status')" />

                        <!-- Validation Errors -->
                        <x-auth-validation-errors class="mb-4" :status="session('errors')" />
                    </div>
                </div>
            </div>
        </div>
    @endif



    <div class="mt-4 mb-0 mr-8 text-right">
        <x-button onclick="window.location='/newHook'">New Hook</x-button>
    </div>


    @if (count($hooks) == 0)
        <div class="my-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <p>You don't have any hooks yet. Click the button to make a new one.</p>
                    </div>
                </div>
            </div>
        @else
            @foreach ($hooks as $hook)
                <div class="my-6">
                    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="p-6 bg-white border-b border-gray-200">
                                <div class="flex justify-between">
                                    <div>{{ $hook->name }}</div>
                                    <div class="flex">
                                        <div class="mr-2">
                                            <form method="post" action="/hook/{{ $hook->id }}/deploy">
                                                @csrf
                                                <x-button disabled>Deploy Now</x-button>
                                            </form>
                                        </div>

                                        <x-button class="text-white"
                                            onclick="window.location='/hook/{{ $hook->id }}/edit'">
                                            <svg version="1.1" width="16" height="16" viewBox="0 0 24 24">
                                                <path fill="white"
                                                    d="M16.84,2.73C16.45,2.73 16.07,2.88 15.77,3.17L13.65,5.29L18.95,10.6L21.07,8.5C21.67,7.89 21.67,6.94 21.07,6.36L17.9,3.17C17.6,2.88 17.22,2.73 16.84,2.73M12.94,6L4.84,14.11L7.4,14.39L7.58,16.68L9.86,16.85L10.15,19.41L18.25,11.3M4.25,15.04L2.5,21.73L9.2,19.94L8.96,17.78L6.65,17.61L6.47,15.29" />
                                            </svg>
                                        </x-button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
    @endif

</x-app-layout>
