<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold mb-4 text-slate-900 dark:text-white"><small class="text-transform: lowercase text-sm">Name:</small> {{ $server->name }}</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg">
                <p class="text-sm text-slate-600 dark:text-slate-400">Hostname</p>
                <div class="mt-1 flex items-center gap-2">
                    <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $server->hostname }}</p>
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(@js($server->hostname))"
                        title="Copy hostname"
                        class="text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors"
                    >
                        <i class="fas fa-copy text-xs"></i>
                    </button>
                </div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg">
                <p class="text-sm text-slate-600 dark:text-slate-400">IP Address</p>
                <p class="text-lg font-semibold text-slate-900 dark:text-white font-mono">{{ $server->ip_address }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Network:{{ $server->location }}</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg">
                <p class="text-sm text-slate-600 dark:text-slate-400">Status</p>
                <p class="text-lg font-semibold">
                    <span class="inline-flex items-center gap-2 px-2 py-1 rounded text-xs font-bold
                        @if($serverStats['status'] === 'online') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                        @endif">
                        {{ ucfirst($serverStats['status']) }}
                    </span>
                </p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg">
                <p class="text-sm text-slate-600 dark:text-slate-400">Environment</p>
                <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ ucfirst($serverStats['environment']) }}</p>
            </div>
        </div>

    </div>

    <!-- Quick Notes Section -->
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Quick Notes</h2>
            @if(!$isEditingNotes)
                <button 
                    wire:click="$set('isEditingNotes', true)"
                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white text-sm font-medium rounded transition"
                >
                    Edit
                </button>
            @endif
        </div>

        @if($isEditingNotes)
            <div class="space-y-3">
                <textarea 
                    wire:model="quickNotes"
                    rows="6"
                    class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                    placeholder="Add quick notes about this server..."
                ></textarea>
                <div class="flex gap-2">
                    <button 
                        wire:click="updateQuickNotes"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition"
                    >
                        Save
                    </button>
                    <button 
                        wire:click="cancelEditingNotes"
                        class="px-4 py-2 bg-slate-300 hover:bg-slate-400 dark:bg-slate-600 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-medium rounded-lg transition"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        @else
            <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                @if($quickNotes)
                    <p class="text-slate-900 dark:text-white whitespace-pre-wrap">{{ $quickNotes }}</p>
                @else
                    <p class="text-slate-600 dark:text-slate-400 italic">No quick notes yet.</p>
                @endif
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6" wire:poll.15s="refreshDiscoveryStatus">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Discovery</h2>
            <div class="flex items-center gap-2">
                <button
                    wire:click="refreshDiscoveryStatus"
                    class="px-3 py-1 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white text-sm font-medium rounded transition"
                >
                    Check now
                </button>
                <div x-data="{ remaining: {{ $discoveryIntervalSeconds }} }" x-init="setInterval(() => { remaining = remaining > 1 ? remaining - 1 : {{ $discoveryIntervalSeconds }}; }, 1000)">
                    <span class="text-xs text-slate-600 dark:text-slate-400">next check in <span x-text="remaining"></span>s</span>
                </div>
            </div>
        </div>

        @php
            $lastCheckedAt = $discoveryLastCheckedAt ? \Carbon\Carbon::parse($discoveryLastCheckedAt) : null;
        @endphp
        <p class="mb-4 text-xs text-slate-600 dark:text-slate-400">
            @if($lastCheckedAt)
                Last checked {{ $lastCheckedAt->diffForHumans() }}
            @else
                Not checked yet
            @endif
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($discoveryCards as $card)
                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ $card['name'] }}</h3>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium
                            @if($card['status'] === 'ready') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                            @elseif($card['status'] === 'warning') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                            @endif">
                            {{ ucfirst($card['status']) }}
                        </span>
                    </div>
                    <p class="mb-1 text-sm text-slate-700 dark:text-slate-300">{{ $card['description'] }}</p>
                    <p class="mb-3 text-xs text-slate-600 dark:text-slate-400">{{ $card['message'] }}</p>

                    @if($card['url'])
                        <a
                            href="{{ $card['url'] }}"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-medium rounded transition"
                        >
                            {{ $card['button'] }}
                        </a>
                    @else
                        <span class="inline-flex items-center px-3 py-2 text-sm bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded">
                            Unavailable
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- SSL Certificates -->
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">SSL Certificates</h2>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="discoverServerSslCertificates"
                    wire:loading.attr="disabled"
                    wire:target="discoverServerSslCertificates"
                    class="text-xs px-3 py-1 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white font-medium rounded transition"
                >
                    <span wire:loading.remove wire:target="discoverServerSslCertificates">Discover via cURL</span>
                    <span wire:loading wire:target="discoverServerSslCertificates">Discovering...</span>
                </button>
                <a
                    href="{{ route('inventory.ssl.create') }}?server={{ $server->id }}"
                    wire:navigate.hover
                    class="text-xs px-3 py-1 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-medium rounded transition"
                >
                    + Add Cert
                </a>
            </div>
        </div>

        @if($sslDiscoveryMessage)
            <div class="mb-3 text-xs font-medium {{ $sslDiscoverySuccess ? 'text-green-700 dark:text-green-300' : 'text-yellow-700 dark:text-yellow-300' }}">
                {{ $sslDiscoveryMessage }}
            </div>
        @endif

        @php $sslCerts = $server->sslCertificates()->orderBy('expires_at')->get(); @endphp

        @if($sslCerts->isEmpty())
            <p class="text-sm text-slate-600 dark:text-slate-400 italic">No SSL certificates tracked for this server.</p>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($sslCerts as $cert)
                    @php
                        $days = $cert->getDaysUntilExpiry();
                        $effectiveStatus = $days === null
                            ? 'unknown'
                            : ($days < 0
                                ? 'expired'
                                : ($days <= 90 ? 'expiring_soon' : 'valid'));
                    @endphp
                    <div wire:key="ssl-{{ $cert->id }}" class="py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 dark:text-white truncate">{{ $cert->domain }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $cert->issuer ?? 'Unknown issuer' }}
                                @if($cert->port !== 443)
                                    &bull; port {{ $cert->port }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-right">
                                @if($cert->expires_at)
                                    <p class="text-xs text-slate-600 dark:text-slate-400">Expires {{ $cert->expires_at->format('Y-m-d') }}</p>
                                    <p class="text-xs font-semibold
                                        {{ $days < 0 ? 'text-red-600 dark:text-red-400' : '' }}
                                        {{ $days >= 0 && $days <= 30 ? 'text-red-600 dark:text-red-400' : '' }}
                                        {{ $days > 30 && $days <= 90 ? 'text-yellow-600 dark:text-yellow-400' : '' }}
                                        {{ $days > 90 ? 'text-green-600 dark:text-green-400' : '' }}
                                    ">
                                        {{ $days < 0 ? 'Expired' : $days . 'd left' }}
                                    </p>
                                @else
                                    <p class="text-xs text-slate-400 dark:text-slate-500">No expiry data</p>
                                @endif
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $effectiveStatus === 'valid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                {{ $effectiveStatus === 'expiring_soon' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                {{ $effectiveStatus === 'expired' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                {{ $effectiveStatus === 'unknown' ? 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200' : '' }}
                            ">
                                {{ ucfirst(str_replace('_', ' ', $effectiveStatus)) }}
                            </span>
                            <a
                                href="{{ route('inventory.ssl.edit', $cert) }}"
                                class="text-xs text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors"
                                title="Edit"
                            >
                                <i class="fas fa-pencil"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('inventory.ssl.index') }}?filterServerId={{ $server->id }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                    View all in SSL Certificates →
                </a>
            </div>
        @endif
    </div>

    
    <!-- Machine Notes are a record of the machine that anyone can edit and update
    these may include helpful code snippets, notes on configuration changes, or troubleshooting steps. -->

    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-white">Machine Notes</h2>
        <div class="space-y-4">
            @forelse($applications as $note)
                <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $note['author'] }} • {{ \Carbon\Carbon::parse($note['created_at'])->diffForHumans() }}</p>
                    <p class="text-slate-900 dark:text-white">{{ $note['content'] }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-600 dark:text-slate-400">No notes found for this machine.</p>
            @endforelse
        </div>
    </div>      


    <!-- Team Chat so that Team members can post chat messages about this host
        and can provide updates on maintenance, issues, or general notes. -->

    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-white">Team Chat</h2>
        <div class="space-y-4">
            @forelse($applications as $chat)
                <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $chat['author'] }} • {{ \Carbon\Carbon::parse($chat['created_at'])->diffForHumans() }}</p>
                    <p class="text-slate-900 dark:text-white">{{ $chat['message'] }}</p>
                </div>
            @empty        
                <p class="text-sm text-slate-600 dark:text-slate-400">No chat messages found for this machine.</p>
            @endforelse
        </div>
    </div>          


    <!-- Services and Stakeholders -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-white text-">What This Host Does</h2>
            <div class="space-y-2">
                @forelse($applications as $app)
                    <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800 rounded">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ $app['name'] }}</p>
                            <p class="text-xs text-slate-600 dark:text-slate-400">{{ $app['type'] }} • v{{ $app['version'] ?? 'unknown' }}</p>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded
                            @if($app['status'] === 'running') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                            @endif">
                            {{ ucfirst($app['status']) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-600 dark:text-slate-400">No services found</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-white">Who This Host Belongs To</h2>
            <div class="space-y-2">
                @forelse($services as $svc)
                    <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800 rounded">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ $svc['name'] }}</p>
                            <p class="text-xs text-slate-600 dark:text-slate-400">{{ $svc['service_name'] }}</p>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded
                            @if($svc['status'] === 'running') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                            @endif">
                            {{ ucfirst($svc['status']) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-600 dark:text-slate-400">No stakeholders found</p>
                @endforelse
            </div>
        </div>

    </div>

</div>
