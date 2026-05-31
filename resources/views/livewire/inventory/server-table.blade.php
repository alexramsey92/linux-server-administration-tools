<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <div class="mb-4 flex flex-wrap items-start gap-3">
            <div class="space-y-1">
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Server Inventory</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Total servers: <span class="font-semibold text-slate-900 dark:text-white">{{ $this->totalServerCount }}</span>
                    @if($search || $filterSubnet || $filterStatus || $filterEnvironment)
                        <span class="ml-2">(matching filters: {{ $servers->total() }})</span>
                    @endif
                </p>
            </div>
            <div class="ml-auto flex gap-2">
                <button
                    wire:click="exportCsv"
                    class="flex items-center gap-2 px-3 py-2 bg-slate-600 hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-medium rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2m0 0v-8m0 8l-6-4m6 4l6-4"></path>
                    </svg>
                    Export CSV
                </button>
                <a 
                    href="{{ route('inventory.scan') }}"
                    class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition"
                >
                    Scan VMs
                </a>
                <a 
                    href="{{ route('inventory.create') }}"
                    class="flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Server
                </a>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-100 p-3 dark:border-slate-700 dark:bg-slate-800">
            <div class="min-w-[220px] flex-1">
                <input 
                    type="text" 
                    wire:model.live="search"
                    placeholder="Search by name, hostname, or IP..."
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
            </div>
            <div class="min-w-[180px]">
                <select
                    wire:model.live="filterSubnet"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 font-mono text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option value="">All Subnets</option>
                    @foreach($availableSubnets as $subnet)
                        <option value="{{ $subnet }}">{{ $subnet }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[160px]">
                <select 
                    wire:model.live="filterStatus"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option value="">All Statuses</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="error">Error</option>
                </select>
            </div>
            <div class="min-w-[170px]">
                <select 
                    wire:model.live="filterEnvironment"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option value="">All Environments</option>
                    <option value="production">Production</option>
                    <option value="staging">Staging</option>
                    <option value="development">Development</option>
                    <option value="testing">Testing</option>
                </select>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button 
                    wire:click="clearFilters"
                    class="px-3 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white text-sm font-medium rounded-md transition whitespace-nowrap"
                >
                    Reset
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b-2 border-slate-300 dark:border-slate-600">
                    <tr>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white cursor-pointer hover:text-blue-500" wire:click="sortBy('name')">
                            Name {{ $sortBy === 'name' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Hostname</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">IP Address</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white cursor-pointer hover:text-blue-500" wire:click="sortBy('status')">
                            Status {{ $sortBy === 'status' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Environment</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Domain</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($servers as $server)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                            <td class="py-4 px-4 font-medium text-slate-900 dark:text-white">
                                <a href="{{ route('inventory.detail', $server) }}" class="hover:text-blue-600 dark:hover:text-blue-400 hover:underline">
                                    {{ $server->name }}
                                </a>
                            </td>
                            <td class="py-4 px-4 text-slate-600 dark:text-slate-400">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('inventory.detail', $server) }}" class="hover:text-blue-600 dark:hover:text-blue-400 hover:underline">
                                        {{ $server->hostname }}
                                    </a>
                                    <button
                                        type="button"
                                        onclick="navigator.clipboard.writeText(@js($server->hostname))"
                                        title="Copy hostname"
                                        class="text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors"
                                    >
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="py-4 px-4 font-mono text-sm text-slate-600 dark:text-slate-400">
                                <a href="{{ route('inventory.detail', $server) }}" class="hover:text-blue-600 dark:hover:text-blue-400 hover:underline">
                                    {{ $server->ip_address }}
                                </a>
                            </td>
                            <td class="py-4 px-4">
                                @php($statusCheckedAt = $server->last_health_check ?? $server->updated_at)
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium
                                    @if($server->status === 'online') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    @elseif($server->status === 'offline') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                    @elseif($server->status === 'maintenance') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                    @else bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200
                                    @endif">
                                    <span class="w-2 h-2 rounded-full
                                        @if($server->status === 'online') bg-green-600
                                        @elseif($server->status === 'offline') bg-red-600
                                        @else bg-slate-600
                                        @endif"></span>
                                    {{ ucfirst($server->status) }}
                                </span>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" title="{{ $statusCheckedAt?->toDayDateTimeString() }}">
                                    @if($statusCheckedAt)
                                        Last checked {{ $statusCheckedAt->diffForHumans() }}
                                    @else
                                        Last checked never
                                    @endif
                                </p>
                            </td>
                            <td class="py-4 px-4 text-slate-600 dark:text-slate-400">
                                <span class="inline-block px-2 py-1 rounded text-xs font-medium
                                    @if($server->environment === 'production') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                    @elseif($server->environment === 'staging') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                    @else bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200
                                    @endif">
                                    {{ ucfirst($server->environment) }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-sm text-slate-600 dark:text-slate-400">
                                <a href="{{ route('inventory.detail', $server) }}" class="hover:text-blue-600 dark:hover:text-blue-400 hover:underline">
                                    <span class="inline-block px-2 py-1 rounded text-xs font-medium
                                    @if($server->domain === 'internal') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                    @else bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200
                                    @endif">
                                    {{ $server->domain === 'internal' ? 'Internal' : 'External' }}
                                    </span>
                                </a>
                            </td>
                            <td class="py-4 px-4 flex gap-2">
                                <a 
                                    href="{{ route('inventory.detail', $server) }}"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium"
                                >
                                    View
                                </a>
                                <a 
                                    href="{{ route('inventory.edit', $server) }}"
                                    class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 text-sm font-medium"
                                >
                                    Edit
                                </a>
                                <button 
                                    wire:click="confirmDeleteServer({{ $server->id }})"
                                    class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 px-4 text-center text-slate-500 dark:text-slate-400">
                                No servers found. <a href="{{ route('inventory.create') }}" class="text-blue-600 hover:underline">Add one now</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $servers->links() }}
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    @if($confirmDelete)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-slate-900 rounded-lg shadow-lg p-6 max-w-sm">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">
                    Delete Server
                </h3>
                <p class="text-slate-600 dark:text-slate-400 mb-6">
                    Are you sure you want to delete this server? This action cannot be undone.
                </p>
                <div class="flex gap-3 justify-end">
                    <button 
                        wire:click="cancelDelete"
                        class="px-4 py-2 bg-slate-300 hover:bg-slate-400 dark:bg-slate-600 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-medium rounded-lg transition"
                    >
                        Cancel
                    </button>
                    <button 
                        wire:click="deleteServer"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 text-white font-medium rounded-lg transition"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
