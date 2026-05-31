<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Server Health Monitor</h1>
            <button wire:click="loadHealthData" wire:loading.attr="disabled" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                <span wire:loading.remove>Refresh</span>
                <span wire:loading>Loading...</span>
            </button>
        </div>

        @if($isLoading)
            <div class="text-center py-8">
                <p class="text-slate-600 dark:text-slate-400">Loading health data...</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800">
                    <p class="text-sm text-slate-600 dark:text-slate-400">CPU Usage</p>
                    <p class="text-3xl font-bold text-slate-900 dark:text-white">{{ $healthSummary['cpu_usage'] ?? 'N/A' }}%</p>
                </div>
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Memory Usage</p>
                    <p class="text-3xl font-bold text-slate-900 dark:text-white">{{ $healthSummary['memory_usage'] ?? 'N/A' }}%</p>
                </div>
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Disk Usage</p>
                    <p class="text-3xl font-bold text-slate-900 dark:text-white">{{ $healthSummary['disk_usage'] ?? 'N/A' }}%</p>
                </div>
                <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800">
                    <p class="text-sm text-slate-600 dark:text-slate-400">Status</p>
                    <p class="text-lg font-bold mt-2">
                        <span class="px-3 py-1 rounded text-sm font-bold
                            @if(($healthSummary['status'] ?? '') === 'healthy') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                            @elseif(($healthSummary['status'] ?? '') === 'warning') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                            @endif">
                            {{ ucfirst($healthSummary['status'] ?? 'unknown') }}
                        </span>
                    </p>
                </div>
            </div>

            @if(!empty($historyData))
                <div class="bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                    <h2 class="text-lg font-bold mb-4 text-slate-900 dark:text-white">History (Last 24 Hours)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <h3 class="font-medium text-slate-900 dark:text-white">CPU Usage</h3>
                            <div class="flex items-end gap-1 h-24 bg-white dark:bg-slate-900 p-2 rounded">
                                @foreach($historyData as $point)
                                    <div class="flex-1 h-full bg-blue-500 rounded-t" style="height: {{ ($point['cpu'] / 100) * 100 }}%;"></div>
                                @endforeach
                            </div>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-medium text-slate-900 dark:text-white">Memory Usage</h3>
                            <div class="flex items-end gap-1 h-24 bg-white dark:bg-slate-900 p-2 rounded">
                                @foreach($historyData as $point)
                                    <div class="flex-1 h-full bg-yellow-500 rounded-t" style="height: {{ ($point['memory'] / 100) * 100 }}%;"></div>
                                @endforeach
                            </div>
                        </div>
                        <div class="space-y-2">
                            <h3 class="font-medium text-slate-900 dark:text-white">Disk Usage</h3>
                            <div class="flex items-end gap-1 h-24 bg-white dark:bg-slate-900 p-2 rounded">
                                @foreach($historyData as $point)
                                    <div class="flex-1 h-full bg-red-500 rounded-t" style="height: {{ ($point['disk'] / 100) * 100 }}%;"></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
