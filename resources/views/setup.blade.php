<x-guest-layout>
    <x-slot name="title">Setup</x-slot>

    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <p class="mb-5"><img src="full_logo.svg" style="height:55px"></p>


                <h1 class="mb-5 text-xl">Set up</h1>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <!-- Validation Errors -->
                <x-auth-validation-errors class="mb-4" :status="session('errors')" />


                <form method="post">
                    @csrf

                    <div class="mb-5">
                        Please create the admin password:<br>
                        <input type="text" name="password" value="{{ $password }}" style="padding:0px 8px">
                    </div>

                    <div class="mb-5">
                        <x-button onclick="window.location = '/'">Do It</x-button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <style>
        .help {
            color: gray;
            padding-left: 20px;
        }

    </style>

</x-guest-layout>
