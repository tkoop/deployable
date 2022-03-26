<x-app-layout>
    <x-slot name="title">{{ $hook->name }}</x-slot>

    <x-slot name="header">{{ $hook->name }}</x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">


									<div class="flex">
										<div class="mr-2">
											<form method="post" action="/hook/{{ $hook->id }}/deploy">
												@csrf
												<x-button class="py-4">Deploy Now</x-button>
											</form>
										</div>

										<x-button-link href="/hook/{{ $hook->id }}/deployments"
											class="mr-2">Deployments</x-button-link>

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
</x-app-layout>