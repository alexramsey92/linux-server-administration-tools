<div class="space-y-6 p-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">SSL Certificates</h1>
            <p class="text-slate-600 dark:text-slate-400 mt-1 text-sm">Track and monitor SSL certificate renewals across your inventory.</p>
        </div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="queueInventoryDiscovery"
                wire:loading.attr="disabled"
                wire:target="queueInventoryDiscovery"
                class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white font-medium rounded-lg transition flex items-center gap-2"
            >
                <i class="fas fa-layer-group" wire:loading.remove wire:target="queueInventoryDiscovery"></i>
                <i class="fas fa-spinner fa-spin" wire:loading wire:target="queueInventoryDiscovery"></i>
                <span wire:loading.remove wire:target="queueInventoryDiscovery">Queue Inventory Discovery</span>
                <span wire:loading wire:target="queueInventoryDiscovery">Queueing...</span>
            </button>
            <a
                href="{{ route('inventory.ssl.create') }}"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition flex items-center gap-2"
            >
                <i class="fas fa-plus"></i>
                Add Certificate
            </a>
        </div>
    </div>

    @if($inventoryDiscoveryMessage)
        <div class="text-sm font-medium text-blue-700 dark:text-blue-300">
            {{ $inventoryDiscoveryMessage }}
        </div>
    @endif

    <!-- Expiry Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-5 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">Expiring within 30 days</p>
                    <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $expiringSoon30 }}</p>
                </div>
                <div class="text-red-500 dark:text-red-400 opacity-60">
                    <i class="fas fa-triangle-exclamation text-3xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-5 border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">Expiring within 60 days</p>
                    <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">{{ $expiringSoon60 }}</p>
                </div>
                <div class="text-yellow-500 dark:text-yellow-400 opacity-60">
                    <i class="fas fa-clock text-3xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-5 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 font-medium">Expiring within 90 days</p>
                    <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $expiringSoon90 }}</p>
                </div>
                <div class="text-blue-500 dark:text-blue-400 opacity-60">
                    <i class="fas fa-calendar text-3xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Export -->
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <input
                    wire:model.live="search"
                    type="text"
                    placeholder="Search domain, issuer, server..."
                    class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <!-- Filter by Status -->
            <div>
                <select
                    wire:model.live="filterStatus"
                    class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">All Statuses</option>
                    <option value="valid">Valid</option>
                    <option value="expiring_soon">Expiring Soon</option>
                    <option value="expired">Expired</option>
                    <option value="unknown">Unknown</option>
                </select>
            </div>

            <!-- Filter by Server -->
            <div>
                <select
                    wire:model.live="filterServerId"
                    class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                >
                    <option value="0">All Servers</option>
                    @foreach($servers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex justify-end mb-4">
            <button
                wire:click="exportCsv"
                class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white font-medium rounded-lg transition flex items-center gap-2"
            >
                <i class="fas fa-download"></i>
                Download CSV Report
            </button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700">
                        <th class="pb-3 pr-4">
                            <button wire:click="sortBy('domain')" class="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400">
                                Domain
                                @if($sortBy === 'domain')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500 text-xs"></i>
                                @else
                                    <i class="fas fa-sort text-slate-400 text-xs"></i>
                                @endif
                            </button>
                        </th>
                        <th class="pb-3 pr-4">
                            <button wire:click="sortBy('server_id')" class="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400">
                                Server
                                @if($sortBy === 'server_id')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500 text-xs"></i>
                                @else
                                    <i class="fas fa-sort text-slate-400 text-xs"></i>
                                @endif
                            </button>
                        </th>
                        <th class="pb-3 pr-4">
                            <button wire:click="sortBy('issuer')" class="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400">
                                Issuer
                                @if($sortBy === 'issuer')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500 text-xs"></i>
                                @else
                                    <i class="fas fa-sort text-slate-400 text-xs"></i>
                                @endif
                            </button>
                        </th>
                        <th class="pb-3 pr-4">
                            <button wire:click="sortBy('expires_at')" class="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400">
                                Expires
                                @if($sortBy === 'expires_at')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500 text-xs"></i>
                                @else
                                    <i class="fas fa-sort text-slate-400 text-xs"></i>
                                @endif
                            </button>
                        </th>
                        <th class="pb-3 pr-4 font-semibold text-slate-700 dark:text-slate-300">Days Left</th>
                        <th class="pb-3 pr-4 font-semibold text-slate-700 dark:text-slate-300">Status</th>
                        <th class="pb-3 pr-4 font-semibold text-slate-700 dark:text-slate-300">Last Checked</th>
                        <th class="pb-3 font-semibold text-slate-700 dark:text-slate-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($certificates as $cert)
                        <tr wire:key="cert-{{ $cert->id }}" class="bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            @php
                                $daysLeft = $cert->getDaysUntilExpiry();
                                $effectiveStatus = $daysLeft === null
                                    ? 'unknown'
                                    : ($daysLeft < 0
                                        ? 'expired'
                                        : ($daysLeft <= 90 ? 'expiring_soon' : 'valid'));
                            @endphp
                            <td class="py-3 pr-4">
                                <div class="font-medium text-slate-900 dark:text-white">{{ $cert->domain }}</div>
                                @if($cert->port !== 443)
                                    <div class="text-xs text-slate-500 dark:text-slate-400">port {{ $cert->port }}</div>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if($cert->server)
                                    <a href="{{ route('inventory.detail', $cert->server) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                        {{ $cert->server->name }}
                                    </a>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-600 dark:text-slate-400 max-w-[180px] truncate" title="{{ $cert->issuer }}">
                                {{ $cert->issuer ?? '—' }}
                            </td>
                            <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                {{ $cert->expires_at?->format('Y-m-d') ?? '—' }}
                            </td>
                            <td class="py-3 pr-4">
                                @if($daysLeft === null)
                                    <span class="text-slate-400 dark:text-slate-500">—</span>
                                @elseif($daysLeft < 0)
                                    <span class="font-semibold text-red-600 dark:text-red-400">Expired</span>
                                @elseif($daysLeft <= 30)
                                    <span class="font-semibold text-red-600 dark:text-red-400">{{ $daysLeft }}d</span>
                                @elseif($daysLeft <= 90)
                                    <span class="font-semibold text-yellow-600 dark:text-yellow-400">{{ $daysLeft }}d</span>
                                @else
                                    <span class="font-semibold text-green-600 dark:text-green-400">{{ $daysLeft }}d</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $effectiveStatus === 'valid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                    {{ $effectiveStatus === 'expiring_soon' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                    {{ $effectiveStatus === 'expired' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                    {{ $effectiveStatus === 'unknown' ? 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200' : '' }}
                                ">
                                    {{ ucfirst(str_replace('_', ' ', $effectiveStatus)) }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-slate-500 dark:text-slate-400 text-xs">
                                {{ $cert->last_checked_at ? $cert->last_checked_at->diffForHumans() : 'Never' }}
                            </td>
                            <td class="py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        wire:click="checkCertificate({{ $cert->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="checkCertificate({{ $cert->id }})"
                                        title="Check Now"
                                        class="p-1.5 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors"
                                    >
                                        <span wire:loading wire:target="checkCertificate({{ $cert->id }})">
                                            <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </span>
                                        <i wire:loading.remove wire:target="checkCertificate({{ $cert->id }})" class="fas fa-rotate text-sm"></i>
                                    </button>
                                    <a
                                        href="{{ route('inventory.ssl.edit', $cert) }}"
                                        title="Edit"
                                        class="p-1.5 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors"
                                    >
                                        <i class="fas fa-pencil text-sm"></i>
                                    </a>
                                    <button
                                        wire:click="confirmDeleteCertificate({{ $cert->id }})"
                                        title="Delete"
                                        class="p-1.5 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 transition-colors"
                                    >
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <i class="fas fa-shield-halved text-4xl mb-3 block opacity-30"></i>
                                No SSL certificates found.
                                <a href="{{ route('inventory.ssl.create') }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 ml-1">Add one now.</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $certificates->links() }}
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    @if($confirmDelete)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Delete Certificate?</h3>
                <p class="text-slate-600 dark:text-slate-400 mb-6">This action cannot be undone.</p>
                <div class="flex gap-3">
                    <button
                        wire:click="deleteCertificate"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition"
                    >
                        Delete
                    </button>
                    <button
                        wire:click="cancelDelete"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-600 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-medium rounded-lg transition"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
