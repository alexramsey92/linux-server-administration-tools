<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <div class="mb-6">
            <a href="{{ route('inventory.ssl.index') }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                ← Back to SSL Certificates
            </a>
        </div>

        <h1 class="text-3xl font-bold mb-6 text-slate-900 dark:text-white">
            {{ $certificate && $certificate->exists ? 'Edit SSL Certificate' : 'Add SSL Certificate' }}
        </h1>

        <form wire:submit.prevent="save" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Server -->
                <div class="md:col-span-2" x-data="{ copied: false }">
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Server *
                    </label>
                    <div class="flex items-center gap-2">
                        <select
                            id="ssl-server-select"
                            wire:model="server_id"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">— Select a server —</option>
                            @foreach($servers as $server)
                                <option value="{{ $server->id }}" data-hostname="{{ $server->hostname }}">{{ $server->name }} ({{ $server->hostname }})</option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            @click="
                                const select = document.getElementById('ssl-server-select');
                                const option = select?.options[select.selectedIndex];
                                const hostname = option?.dataset?.hostname;
                                if (!hostname) return;
                                navigator.clipboard.writeText(hostname);
                                copied = true;
                                setTimeout(() => copied = false, 1200);
                            "
                            class="px-3 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg text-xs font-medium transition whitespace-nowrap"
                        >
                            <span x-show="!copied">Copy Hostname</span>
                            <span x-show="copied">Copied</span>
                        </button>
                    </div>
                    @error('server_id') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Domain -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Domain *
                    </label>
                    <input
                        wire:model="domain"
                        type="text"
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g., app.example.com"
                    >
                    @error('domain') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Port -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Port *
                    </label>
                    <input
                        wire:model="port"
                        type="number"
                        min="1"
                        max="65535"
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        placeholder="443"
                    >
                    @error('port') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Discover Button -->
            <div class="flex items-center gap-4">
                <button
                    type="button"
                    wire:click="discoverCertificate"
                    wire:loading.attr="disabled"
                    class="px-6 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white font-medium rounded-lg transition flex items-center gap-2"
                >
                    <span wire:loading wire:target="discoverCertificate">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <i wire:loading.remove wire:target="discoverCertificate" class="fas fa-search"></i>
                    <span wire:loading wire:target="discoverCertificate">Checking...</span>
                    <span wire:loading.remove wire:target="discoverCertificate">Discover Certificate</span>
                </button>

                @if($checkMessage)
                    <span class="text-sm font-medium {{ $checkSuccess ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $checkMessage }}
                    </span>
                @endif
            </div>

            <!-- Auto-populated fields -->
            @if($issuer || $expires_at)
                <div class="border-t border-slate-200 dark:border-slate-700 pt-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wide">
                        Certificate Details
                        <span class="text-xs font-normal text-slate-500 dark:text-slate-400 ml-2 normal-case">auto-populated from discovery</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Issuer</label>
                            <div class="w-full px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm">
                                {{ $issuer ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Subject / CN</label>
                            <div class="w-full px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm">
                                {{ $subject ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Valid From</label>
                            <div class="w-full px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm">
                                {{ $valid_from ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Expires At</label>
                            <div class="w-full px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-sm">
                                <span class="{{ $status === 'expired' ? 'text-red-600 dark:text-red-400' : ($status === 'expiring_soon' ? 'text-yellow-600 dark:text-yellow-400' : 'text-slate-700 dark:text-slate-300') }}">
                                    {{ $expires_at ?? '—' }}
                                </span>
                                @if($status)
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-medium
                                        {{ $status === 'valid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                        {{ $status === 'expiring_soon' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                        {{ $status === 'expired' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                        {{ $status === 'unknown' ? 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200' : '' }}
                                    ">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                @endif
                            </div>
                        </div>

                        @if(count($sans) > 0)
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Subject Alternative Names (SANs)</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($sans as $san)
                                        <span class="px-3 py-1 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-sm rounded-full border border-blue-200 dark:border-blue-700">
                                            {{ $san }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Buttons -->
            <div class="flex gap-3 pt-2">
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition"
                >
                    {{ $certificate && $certificate->exists ? 'Update Certificate' : 'Save Certificate' }}
                </button>
                <a
                    href="{{ route('inventory.ssl.index') }}"
                    class="px-6 py-2 bg-slate-300 hover:bg-slate-400 dark:bg-slate-600 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-medium rounded-lg transition"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
