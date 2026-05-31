<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6 space-y-6">
        <div class="flex flex-wrap items-start gap-3">
            <div class="space-y-1">
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Discovery</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Audit discovered machines and their imported inventory records.
                </p>
            </div>

            <div class="ml-auto flex gap-2">
                <a
                    href="{{ route('inventory.create') }}"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition"
                >
                    Add Server
                </a>
                <a
                    href="{{ route('inventory.scan') }}"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-medium rounded-lg transition"
                >
                    Scan VMs
                </a>
                <a
                    href="{{ route('inventory.index') }}"
                    class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white font-medium rounded-lg transition"
                >
                    Back to Inventory
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Discovered</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $this->discoveredCount }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Machines found through scan imports.</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Imported</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $this->importedCount }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Inventory records created from discoveries.</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Sources</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ count($this->availableSourceFilters) }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Hostname, wildcard, and subnet discovery modes.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-100 p-3 dark:border-slate-700 dark:bg-slate-800">
            <div class="min-w-[240px] flex-1">
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Search by IP, hostname, or server name..."
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
            </div>
            <div class="min-w-[200px]">
                <select
                    wire:model.live="filterSource"
                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:ring-2 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option value="">All Discovery Sources</option>
                    @foreach($this->availableSourceFilters as $source)
                        <option value="{{ $source }}">{{ str($source)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b-2 border-slate-300 dark:border-slate-600">
                    <tr>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Audit Status</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">IP Address</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Hostname</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Server Name</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Scan Source</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Scan Input</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Discovered</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Imported</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Status</th>
                        <th class="pb-3 px-4 font-semibold text-slate-900 dark:text-white">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($servers as $server)
                        @php($scanSource = data_get($server->metadata, 'scan_source'))
                        @php($scanInput = data_get($server->metadata, 'scan_input'))
                        @php($discoveredAt = data_get($server->metadata, 'scanned_at'))
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                            <td class="py-3 px-4">
                                <span class="inline-flex px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Imported
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-sm text-slate-900 dark:text-white">{{ $server->ip_address }}</td>
                            <td class="py-3 px-4 text-slate-700 dark:text-slate-300">{{ $server->hostname }}</td>
                            <td class="py-3 px-4 text-slate-700 dark:text-slate-300">{{ $server->name }}</td>
                            <td class="py-3 px-4 text-slate-700 dark:text-slate-300">
                                @if($scanSource)
                                    <span class="inline-flex px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        {{ str($scanSource)->replace('_', ' ')->title() }}
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                                        Manual/Unknown
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-sm text-slate-700 dark:text-slate-300 font-mono">{{ $scanInput ?: '—' }}</td>
                            <td class="py-3 px-4 text-sm text-slate-700 dark:text-slate-300" title="{{ $discoveredAt ?: '' }}">
                                {{ $discoveredAt ? \Carbon\Carbon::parse($discoveredAt)->diffForHumans() : '—' }}
                            </td>
                            <td class="py-3 px-4 text-sm text-slate-700 dark:text-slate-300" title="{{ $server->created_at?->toDayDateTimeString() }}">
                                {{ $server->created_at?->diffForHumans() }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex px-2 py-1 rounded text-xs font-medium
                                    @if($server->status === 'online') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    @elseif($server->status === 'offline') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                    @elseif($server->status === 'maintenance') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                    @else bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200
                                    @endif">
                                    {{ ucfirst($server->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <a
                                    href="{{ route('inventory.detail', $server) }}"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 px-4 text-center text-slate-500 dark:text-slate-400">
                                No discovered inventory records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $servers->links() }}
        </div>
    </div>
</div>
