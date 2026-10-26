<x-hook-layout :hook='$hook'>
	<x-slot name="title">{{ $hook->name }} - site</x-slot>

	<x-slot name="header">{{ $hook->name }} - site</x-slot>

	@if (session()->has('output'))
		<div class="mb-5 p-3 rounded {{ session('outputOk') ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900' }}">
			<pre class="text-sm whitespace-pre-wrap">{{ session('output') }}</pre>
		</div>
	@endif

	{{-- The checklist comes first: nothing on this page is worth filling in
	     while something above it would stop the site being served. --}}
	<x-checklist :checks="$checks" title="Everything this site needs" />

	@if ($site)
		<div class="mb-2 text-gray-500 text-sm">
			<span class="font-mono">{{ $site->vhostPath() }}</span>
			@if (!is_file($site->vhostPath()))
				&mdash; doesn't exist yet, saving will create it.
			@endif
		</div>

		@if ($status && count($status['rejected']) > 0)
			<div class="mb-4 p-3 bg-red-50 text-red-900 rounded text-sm">
				Apache refused this site's config:
				<ul class="mt-1 list-disc list-inside">
					@foreach ($status['rejected'] as $entry)
						<li><span class="font-mono">{{ $entry['file'] }}</span>: {{ $entry['reason'] }}</li>
					@endforeach
				</ul>
				<div class="mt-2">The file was moved aside, so the rest of the server kept loading. Fix it
					and save again.</div>
			</div>
		@endif

		@if ($status && $status['pending'])
			<div class="mb-4 p-3 bg-amber-50 text-amber-900 rounded text-sm">
				The config has been written but Apache hasn't picked it up yet.
				Refresh in a moment, or see the checklist above.
			</div>
		@endif
	@endif

	<form method="post" action="/hook/{{ $hook->id }}/site">
		@csrf

		<div class="mb-5">
			<label>Domain</label><br>
			<x-input name="domain" type="text" class="w-full" placeholder="example.com"
				value="{{ old('domain', $subject->domain) }}" />
			<div class="text-gray-400 text-xs mt-1">
				Where this site answers. It's also the file name in
				<span class="font-mono">{{ config('deployable.vhost_dir') }}</span>.
			</div>
		</div>

		<div class="mb-5">
			<label>Aliases</label><br>
			<x-textarea name="aliases" class="w-full font-mono" rows="3"
				placeholder="www.example.com&#10;blog.example.com">{{ old('aliases', $subject->aliasesText()) }}</x-textarea>
			<div class="text-gray-400 text-xs mt-1">
				Other host names this site should answer on, one per line (or comma-separated).
				Apache serves all of them from the same config.
			</div>
		</div>
			<x-input name="document_root" type="text" class="w-full font-mono"
				value="{{ old('document_root', $subject->document_root) }}" />
			<div class="text-gray-400 text-xs mt-1">
				What Apache serves. For a Laravel project that's usually the project's
				<span class="font-mono">public</span> directory.
			</div>
		</div>

		<div class="mb-5">
			<label>Type</label><br>
			<select name="type"
				class="rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200/50">
				<option value="php" @selected(old('type', $subject->type) === 'php')>PHP</option>
				<option value="static" @selected(old('type', $subject->type) === 'static')>Static files</option>
			</select>
		</div>

		<div class="mb-5">
			<label>PHP version</label><br>
			@if (count($phpVersions) > 0)
				<select name="php_version"
					class="rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200/50">
					@foreach ($phpVersions as $version)
						<option value="{{ $version }}"
							@selected(old('php_version', $subject->php_version) === $version)>PHP {{ $version }}</option>
					@endforeach
				</select>
				<div class="text-gray-400 text-xs mt-1">Ignored for static sites.</div>
			@else
				<x-input name="php_version" type="text" class="w-full" placeholder="8.3"
					value="{{ old('php_version', $subject->php_version) }}" />
				<div class="text-gray-400 text-xs mt-1">
					No php-fpm is installed on this server, so nothing can be picked from.
					See the checklist above.
				</div>
			@endif
		</div>

		<div class="mb-5">
			<label>Let's Encrypt email</label><br>
			<x-input name="ssl_email" type="text" class="w-full" placeholder="you@example.com"
				value="{{ old('ssl_email', $subject->ssl_email) }}" />
			<div class="text-gray-400 text-xs mt-1">
				Optional, but Let's Encrypt emails you here when a certificate is about to expire.
			</div>
		</div>

		<div class="flex flex-wrap gap-2 items-center">
			<x-button>{{ $site ? 'Save' : 'Create site' }}</x-button>

			@if ($site)
				@if ($site->enabled)
					<form method="post" action="/hook/{{ $hook->id }}/site/action">
						@csrf
						<input type="hidden" name="disable">
						<x-button-light>Disable</x-button-light>
					</form>
				@else
					<form method="post" action="/hook/{{ $hook->id }}/site/action">
						@csrf
						<input type="hidden" name="enable">
						<x-button>Enable</x-button>
					</form>
				@endif

				<form method="post" action="/hook/{{ $hook->id }}/site/action"
					onsubmit="return confirm('Delete the Apache config for {{ $site->domain }}? The project directory and its files are left alone.')">
					@csrf
					<input type="hidden" name="delete_site">
					<x-button-light class="text-red-700">Delete site</x-button-light>
				</form>
			@endif
		</div>

		@if ($site)
			<div class="text-gray-400 text-xs mt-3">
				Saving rewrites the config from Deployable's template, so anything hand-edited
				in {{ $site->vhostPath() }} won't survive it.
			</div>
		@endif
	</form>

	@if ($site)
		<div class="mt-6 p-3 border rounded">
			<div class="font-medium mb-2">HTTPS</div>

			@if ($site->ssl_state === 'active')
				<div class="text-sm mb-2">
					&check; Certificate in place, serving
					<a class="underline" href="https://{{ $site->domain }}" target="_blank" rel="noreferrer">https://{{ $site->domain }}</a>.
				</div>
				<form method="post" action="/hook/{{ $hook->id }}/site/action">
					@csrf
					<input type="hidden" name="remove_ssl">
					<x-button-light>Turn HTTPS off</x-button-light>
				</form>

			@elseif ($site->ssl_state === 'pending')
				<div class="text-sm mb-2">
					A certificate request is outstanding for {{ $site->domain }}. The site keeps
					serving on http until the answer comes back.
				</div>
				<form method="post" action="/hook/{{ $hook->id }}/site/action">
					@csrf
					<input type="hidden" name="check_ssl">
					<x-button>Check the certificate</x-button>
				</form>

			@elseif ($site->ssl_state === 'failed')
				<div class="text-sm mb-2 text-red-800">The last request was refused:</div>
				@if ($site->ssl_output)
					<pre class="mb-2 p-2 bg-gray-100 text-xs rounded overflow-x-auto whitespace-pre-wrap">{{ $site->ssl_output }}</pre>
				@endif
				<div class="flex gap-2">
					<form method="post" action="/hook/{{ $hook->id }}/site/action">
						@csrf
						<input type="hidden" name="request_ssl">
						<x-button>Try again</x-button>
					</form>
					<form method="post" action="/hook/{{ $hook->id }}/site/action">
						@csrf
						<input type="hidden" name="remove_ssl">
						<x-button-light>Give up on HTTPS</x-button-light>
					</form>
				</div>

			@else
				@if (empty($site->ssl_email))
					<div class="text-sm mb-2 text-amber-800">
						Set a Let's Encrypt email in the form above and save first &mdash;
						certbot needs a reachable address for expiry warnings.
					</div>
				@else
					<div class="text-sm mb-2">
						No certificate yet. The site is serving plain http. Asking Let's
						Encrypt takes a few seconds and needs the site to be reachable on
						port 80.
					</div>
				@endif
				<div class="flex gap-2">
					<form method="post" action="/hook/{{ $hook->id }}/site/action">
						@csrf
						<input type="hidden" name="request_ssl">
						<x-button>Request certificate</x-button>
					</form>
				</div>
			@endif
		</div>
	@endif

	@if (count($candidates) > 0)
		<div class="mt-6 p-3 border rounded">
			<div class="font-medium">Existing site on this server</div>
			<div class="text-sm text-gray-600 mt-1 mb-3">
				Apache already has configs that Deployable doesn't manage, and it won't invent
				them: not every site on a server belongs here. To bring one under this hook,
				copy its file into the site directory &mdash; the commands below do exactly
				that, in the right order &mdash; and then press Connect.
				The file landing there is what triggers the reload, so there's no separate
				reload step to remember.
			</div>

			@foreach ($candidates as $candidate)
				<div class="border-t pt-3 mt-3 first:border-t-0 first:pt-0 first:mt-0">
					<div class="font-mono">{{ $candidate['domain'] }}</div>
					<div class="text-xs text-gray-500 mb-2">
						{{ $candidate['file'] }}
						@if ($candidate['in_dropin'])
							<span class="text-green-700">&mdash; already in the site directory</span>
						@elseif ($candidate['enabled'])
							&mdash; enabled
						@else
							&mdash; present but disabled
						@endif
						@if ($candidate['ssl'])
							&mdash; has an SSL config
						@endif
					</div>

					@if ($candidate['claimed_by'] !== null)
						<div class="text-sm text-gray-600">
							This one is already a site on the &ldquo;{{ $candidate['claimed_by'] }}&rdquo; hook.
							One hook serves one domain, so there's nothing to connect here.
						</div>
					@else
						@if (count($candidate['commands']) > 0)
							<pre class="p-2 bg-gray-900 text-gray-100 text-xs rounded overflow-x-auto select-all">{{ implode("\n", $candidate['commands']) }}</pre>
							<div class="text-xs text-gray-500 mt-1">
								Run those as root, then press Connect. If you've already run them,
								that's what the button checks.
							</div>
						@endif

						<form method="post" action="/hook/{{ $hook->id }}/site/connect" class="mt-2">
							@csrf
							<input type="hidden" name="domain" value="{{ $candidate['domain'] }}">
							<x-button>Connect this hook</x-button>
						</form>
					@endif
				</div>
			@endforeach
		</div>
	@endif
</x-hook-layout>
