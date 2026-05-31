<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6 space-y-6">
        <div class="flex items-center gap-2">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">VM Scanner</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Discover VMs by hostname or subnet and import them into inventory.
                </p>
            </div>
            <a
                href="{{ route('inventory.scanned-ips') }}"
                class="ml-auto px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white rounded-lg text-sm font-medium transition"
            >
                View Discovery
            </a>
            <a
                href="{{ route('inventory.index') }}"
                class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm font-medium transition"
            >
                Back to Inventory
            </a>
        </div>

        {{-- Mode tabs --}}
        <div class="flex gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg w-fit">
            <button
                type="button"
                wire:click="$set('mode', 'hostname')"
                class="px-4 py-2 rounded-md text-sm font-medium transition {{ $mode === 'hostname' ? 'bg-white dark:bg-slate-700 shadow text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
            >
                By Hostname
            </button>
            <button
                type="button"
                wire:click="$set('mode', 'wildcard')"
                class="px-4 py-2 rounded-md text-sm font-medium transition {{ $mode === 'wildcard' ? 'bg-white dark:bg-slate-700 shadow text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
            >
                IP Wildcard
            </button>
            <button
                type="button"
                wire:click="$set('mode', 'subnet')"
                class="px-4 py-2 rounded-md text-sm font-medium transition {{ $mode === 'subnet' ? 'bg-white dark:bg-slate-700 shadow text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}"
            >
                By Subnet (CIDR)
            </button>
        </div>

        <form wire:submit.prevent="scan" class="space-y-4">
            @if($mode === 'hostname')
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                            Hostnames <span class="font-normal text-slate-500">(one per line, short name or FQDN)</span>
                        </label>
                        <textarea
                            wire:model="hostnameInput"
                            rows="5"
                            placeholder="web01.example.com&#10;db01.internal.example.com&#10>app-staging01.example.com&#10;10.10.5.22"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        ></textarea>
                        @error('hostnameInput') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Timeout (sec)</label>
                            <input
                                type="number"
                                wire:model="hostnameTimeout"
                                min="0.1" max="10" step="0.1"
                                class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                            >
                            @error('hostnameTimeout') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex items-end flex-1">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white rounded-lg font-medium transition"
                            >
                                <span wire:loading.remove wire:target="scan">Scan</span>
                                <span wire:loading wire:target="scan">Scanning...</span>
                            </button>
                        </div>
                    </div>
                </div>
            @elseif($mode === 'wildcard')
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                            IP Pattern
                            <span class="font-normal text-slate-500">Use * for any octet or 1-50 for ranges</span>
                        </label>
                        <input
                            type="text"
                            wire:model="wildcardPattern"
                            placeholder="10.162.*.* or 10.162.5.1-50"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 font-mono"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Examples: <code class="bg-slate-100 dark:bg-slate-800 px-1 rounded">10.162.*.*</code>
                            &nbsp;<code class="bg-slate-100 dark:bg-slate-800 px-1 rounded">10.162.5.*</code>
                            &nbsp;<code class="bg-slate-100 dark:bg-slate-800 px-1 rounded">10.162.1-20.*</code>
                        </p>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                            Large ranges use parallel probing. Only hosts with SSH open are shown as Online — offline hosts are still listed.
                        </p>
                        @error('wildcardPattern') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Timeout (sec)</label>
                        <input
                            type="number"
                            wire:model="wildcardTimeout"
                            min="0.1" max="2" step="0.1"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                        @error('wildcardTimeout') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2 mt-3">Max Hosts</label>
                        <input
                            type="number"
                            wire:model="wildcardMaxHosts"
                            min="1" max="65536" step="1"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                        @error('wildcardMaxHosts') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white rounded-lg font-medium transition"
                        >
                            <span wire:loading.remove wire:target="scan">Scan</span>
                            <span wire:loading wire:target="scan">Scanning...</span>
                        </button>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">CIDR Range</label>
                        <input
                            type="text"
                            wire:model="cidr"
                            placeholder="10.10.20.0/24"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                        @error('cidr') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">Timeout (seconds)</label>
                        <input
                            type="number"
                            wire:model="timeout"
                            min="0.1" max="2" step="0.1"
                            class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                        @error('timeout') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-end">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white rounded-lg font-medium transition"
                        >
                            <span wire:loading.remove wire:target="scan">Scan</span>
                            <span wire:loading wire:target="scan">Scanning...</span>
                        </button>
                    </div>
                </div>
            @endif
        </form>

        @error('scan')
            <div class="p-3 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm">
                {{ $message }}
            </div>
        @enderror

        @if(! empty($results))
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    wire:click="selectReachable"
                    class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition"
                >
                    Select Reachable
                </button>
                <button
                    type="button"
                    wire:click="importSelected"
                    class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition"
                >
                    Import Selected
                </button>
                <button
                    type="button"
                    wire:click="clearResults"
                    class="px-3 py-2 bg-slate-500 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition"
                >
                    Clear
                </button>
                <p class="text-sm text-slate-600 dark:text-slate-400 ml-auto">
                    {{ count($results) }} hosts scanned
                </p>
            </div>

            @error('import')
                <div class="p-3 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm">
                    {{ $message }}
                </div>
            @enderror

            @if($importedCount > 0)
                <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm">
                    Imported {{ $importedCount }} VM records into inventory.
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b-2 border-slate-300 dark:border-slate-600">
                        <tr>
                            <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">Select</th>
                            @if($mode === 'hostname')
                                <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">Input</th>
                                <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">Resolved IP</th>
                            @else
                                <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">IP Address</th>
                                <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">Hostname</th>
                            @endif
                            <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">Domain</th>
                            <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">SSH</th>
                            <th class="pb-3 px-3 text-slate-900 dark:text-white font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($results as $row)
                            @php
                                $canSelect = ! empty($row['ip_address']) && filter_var($row['ip_address'], FILTER_VALIDATE_IP);
                                $statusColor = match($row['status'] ?? '') {
                                    'online'     => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                    'offline'    => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                    'unresolved' => 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
                                    default      => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition {{ ! $canSelect ? 'opacity-50' : '' }}">
                                <td class="py-3 px-3">
                                    @if($canSelect)
                                        <input
                                            type="checkbox"
                                            wire:model="selectedIps"
                                            value="{{ $row['ip_address'] }}"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                    @else
                                        <span class="text-slate-400 text-xs">—</span>
                                    @endif
                                </td>
                                @if($mode === 'hostname')
                                    <td class="py-3 px-3 font-mono text-sm text-slate-900 dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $row['input'] ?? $row['fqdn'] ?? '—' }}</span>
                                            @if(! empty($row['input']) || ! empty($row['fqdn']))
                                                <button
                                                    type="button"
                                                    onclick="navigator.clipboard.writeText(@js($row['input'] ?? $row['fqdn']))"
                                                    title="Copy hostname"
                                                    class="text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors"
                                                >
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-sm {{ $canSelect ? 'text-slate-900 dark:text-white' : 'text-amber-600 dark:text-amber-400' }}">
                                        {{ $row['ip_address'] ?? 'Unresolved' }}
                                    </td>
                                @else
                                    <td class="py-3 px-3 font-mono text-sm text-slate-900 dark:text-white">{{ $row['ip_address'] }}</td>
                                    <td class="py-3 px-3 text-slate-700 dark:text-slate-300">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $row['hostname'] ?? '—' }}</span>
                                            @if(! empty($row['hostname']))
                                                <button
                                                    type="button"
                                                    onclick="navigator.clipboard.writeText(@js($row['hostname']))"
                                                    title="Copy hostname"
                                                    class="text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors"
                                                >
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td class="py-3 px-3 text-slate-700 dark:text-slate-300">{{ $row['domain'] ?? '—' }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium {{ ($row['ssh_reachable'] ?? false) ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
                                        {{ ($row['ssh_reachable'] ?? false) ? 'Open' : 'Closed' }}
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium {{ $statusColor }}">
                                        {{ ucfirst($row['status'] ?? 'unknown') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
