<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <div class="mb-6">
            <a href="{{ route('inventory.index') }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                ← Back to Inventory
            </a>
        </div>

        <h1 class="text-3xl font-bold mb-6 text-slate-900 dark:text-white">
            {{ $server ? 'Edit Server' : 'Create New Server' }}
        </h1>

        <!-- Hostname conventions guide -->
        <div x-data="{ expanded: false }" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
            <button 
                @click="expanded = !expanded" 
                type="button"
                class="w-full flex items-center justify-between hover:opacity-75 transition-opacity"
            >
                <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-300">Hostname Conventions</h2>
                <svg class="h-5 w-5 text-blue-900 dark:text-blue-300 transition-transform" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </button>
            
            <div x-show="expanded" x-transition class="text-sm text-blue-800 dark:text-blue-400 space-y-2 mt-3">
                <p><strong>Format:</strong> prefix-role-number-tier</p>
                <p><strong>Example:</strong> <code class="bg-white dark:bg-slate-800 px-2 py-1 rounded">web-frontend01p</code> → public web server, instance 01, production</p>
                <p class="mt-2"><strong>Tier Detection (ending letter):</strong></p>
                <ul class="list-disc list-inside pl-2 space-y-1">
                    <li><strong>d</strong> = Development</li>
                    <li><strong>t</strong> = Test/Staging</li>
                    <li><strong>p</strong> = Production</li>
                    <li>No letter = Production</li>
                </ul>
            </div>
        </div>

        <form wire:submit.prevent="save" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Hostname (First) -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Hostname (machine name) *
                    </label>
                    <input 
                        wire:model.live="hostname"
                        type="text"
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g., web-frontend01p"
                    >
                    @if($this->fullHostname)
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText(@js($this->fullHostname))"
                            class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
                        >
                            <i class="fas fa-copy"></i>
                            Copy full hostname ({{ $this->fullHostname }})
                        </button>
                    @endif
                    @if($this->getHostnameValidationMessage())
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-2">
                            {{ $this->getHostnameValidationMessage() }}
                        </p>
                    @endif
                    @error('hostname') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Domain Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Domain *
                    </label>
                    <select 
                        wire:model.live="domain"
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                    >
                        @foreach($domainOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('domain') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Server Name (Auto-populated) -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Server Name (auto-populated) *
                    </label>
                    <input 
                        wire:model="name"
                        type="text"
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        placeholder="Auto-populated from hostname"
                        readonly
                    >
                    @error('name') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- IP Address with intelligent discovery -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        IP Address *
                        @if($isDiscovering)
                            <span class="text-xs text-blue-600 dark:text-blue-400 ml-2">Discovering...</span>
                        @elseif($discoveryMessage)
                            <span class="text-xs {{ $discoverySuccess ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} ml-2">
                                {{ $discoveryMessage }}
                            </span>
                        @endif
                    </label>
                    <div class="relative">
                        <input 
                            wire:model="ip_address"
                            wire:click="discoverHostname"
                            type="text"
                            class="w-full px-4 py-2 border-2 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 cursor-pointer
                            {{ $discoverySuccess ? 'border-green-500 dark:border-green-500' : ($discoveryMessage && !$isDiscovering ? 'border-red-500 dark:border-red-500' : 'border-slate-300 dark:border-slate-600') }}"
                            placeholder="Click to discover from hostname..."
                            {{ $isDiscovering ? 'disabled' : '' }}
                        >
                        @if($isDiscovering)
                            <div class="absolute right-3 top-3">
                                <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        @elseif($discoverySuccess)
                            <div class="absolute right-3 top-3 text-green-600">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        @elseif($discoveryMessage && !$isDiscovering)
                            <div class="absolute right-3 top-3 text-red-600">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        @endif
                    </div>
                    @error('ip_address') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Status *
                    </label>
                    <select 
                        wire:model="status"
                        class="w-full px-4 py-2 border-2 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500
                        {{ $discoverySuccess === true ? 'border-green-500 dark:border-green-500 bg-green-50 dark:bg-green-900/20' : ($discoverySuccess === false ? 'border-red-500 dark:border-red-500 bg-red-50 dark:bg-red-900/20' : 'text-slate-600 dark:text-slate-400 cursor-not-allowed disabled') }}"
                        >
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="error">Error</option>
                    </select>
                    @error('status') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Environment -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Environment *
                        <span class="text-xs text-slate-500 dark:text-slate-400">(auto-detected from hostname tier)</span>
                    </label>
                    <select 
                        wire:model="environment"
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="production">Production</option>
                        <option value="staging">Staging</option>
                        <option value="development">Development</option>
                    </select>
                    @error('environment') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- OS -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        Operating System *
                    </label>
                    <div class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-white">
                        Oracle
                    </div>
                </div>

                <!-- OS Version -->
                <div>
                    <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">
                        OS Version *
                    </label>
                    <select 
                        wire:model="os_version"
                        class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="8">8 (Default)</option>
                        <option value="9">9</option>
                        <option value="7">7</option>
                        <option value="10">10</option>
                    </select>
                    @error('os_version') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Links & Tools Section -->
            @if($server || ($hostname && $domain))
                <div class="border-t border-slate-200 dark:border-slate-700 pt-6 mt-6">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Links & Tools</h3>
                    
                    <div x-data="{ copiedField: null }" class="space-y-4">
                        <!-- Splunk Query Field -->
                        @if($this->splunkQuery)
                            <div class="bg-slate-50 dark:bg-slate-800 rounded-lg p-4 space-y-2">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Splunk Query
                                </label>
                                <div class="flex gap-2">
                                    <input 
                                        type="text"
                                        value="{{ $this->splunkQuery }}"
                                        readonly
                                        class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded font-mono text-sm text-slate-900 dark:text-white"
                                    >
                                    <button 
                                        type="button"
                                        @click="
                                            navigator.clipboard.writeText('{{ $this->splunkQuery }}');
                                            copiedField = 'splunk';
                                            setTimeout(() => copiedField = null, 2000);
                                        "
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded transition whitespace-nowrap"
                                        :class="{ 'bg-green-600 hover:bg-green-700': copiedField === 'splunk' }"
                                    >
                                        <span x-show="copiedField !== 'splunk'">Copy</span>
                                        <span x-show="copiedField === 'splunk'">✓ Copied</span>
                                    </button>
                                </div>
                                @if($this->splunkSearchUrl)
                                    <a 
                                        href="{{ $this->splunkSearchUrl }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-2 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        Open in Splunk →
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        @endif

                        <!-- External Links Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- ServiceNow Link -->
                            @if($this->serviceNowSearchUrl)
                                <a 
                                    href="{{ $this->serviceNowSearchUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-2 px-4 py-3 bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600 text-white font-medium rounded-lg transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    ServiceNow
                                </a>
                            @endif

                            <!-- Machine SSH Link -->
                            @if($this->machineAccessUrl)
                                <a 
                                    href="{{ $this->machineAccessUrl }}"
                                    class="inline-flex items-center gap-2 px-4 py-3 bg-purple-600 hover:bg-purple-700 dark:bg-purple-700 dark:hover:bg-purple-600 text-white font-medium rounded-lg transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    SSH Access
                                </a>
                            @endif
                        </div>

                        <!-- Additional Info -->
                        <div class="text-xs text-slate-600 dark:text-slate-400">
                            <p class="italic">💡 Links are generated from hostname, domain, and SSH user. Update fields above to refresh.</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Buttons -->
            <div class="flex gap-3">
                <button 
                    type="submit"
                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white font-medium rounded-lg transition"
                >
                    {{ $server ? 'Update Server' : 'Create Server' }}
                </button>
                <a 
                    href="{{ route('inventory.index') }}"
                    class="px-6 py-2 bg-slate-300 hover:bg-slate-400 dark:bg-slate-600 dark:hover:bg-slate-700 text-slate-900 dark:text-white font-medium rounded-lg transition"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
