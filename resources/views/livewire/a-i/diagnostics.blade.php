<div class="space-y-6 p-6">
    <div class="bg-white dark:bg-slate-900 rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold mb-6 text-slate-900 dark:text-white">AI Server Diagnostics</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <button 
                wire:click="runDiagnostic('health')"
                wire:loading.attr="disabled"
                class="p-4 rounded-lg border-2 transition
                    @if($selectedDiagnostic === 'health') border-blue-500 bg-blue-50 dark:bg-blue-900
                    @else border-slate-300 dark:border-slate-600 hover:border-blue-500
                    @endif disabled:opacity-50">
                <p class="font-bold text-slate-900 dark:text-white">Health Analysis</p>
                <p class="text-sm text-slate-600 dark:text-slate-400">Analyze server health</p>
            </button>

            <button 
                wire:click="runDiagnostic('deployment')"
                wire:loading.attr="disabled"
                class="p-4 rounded-lg border-2 transition
                    @if($selectedDiagnostic === 'deployment') border-blue-500 bg-blue-50 dark:bg-blue-900
                    @else border-slate-300 dark:border-slate-600 hover:border-blue-500
                    @endif disabled:opacity-50">
                <p class="font-bold text-slate-900 dark:text-white">Deployment</p>
                <p class="text-sm text-slate-600 dark:text-slate-400">Get recommendations</p>
            </button>

            <div class="p-4 rounded-lg border-2 border-slate-300 dark:border-slate-600">
                <p class="font-bold text-slate-900 dark:text-white mb-2">Custom Issue</p>
                <input 
                    type="text"
                    wire:model="customIssue"
                    placeholder="Describe issue..."
                    class="w-full px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                <button 
                    wire:click="runDiagnostic('issue')"
                    wire:loading.attr="disabled"
                    class="mt-2 w-full px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50">
                    Diagnose
                </button>
            </div>
        </div>

        @if($isLoading)
            <div class="bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                <p class="text-center text-slate-600 dark:text-slate-400">Claude AI is analyzing your server...</p>
            </div>
        @elseif(!empty($diagnosis))
            <div class="bg-slate-50 dark:bg-slate-800 p-6 rounded-lg">
                <h2 class="font-bold text-slate-900 dark:text-white mb-4">Analysis Result</h2>
                <div class="prose dark:prose-invert max-w-none text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                    {{ $diagnosis }}
                </div>
            </div>
        @else
            <div class="bg-slate-50 dark:bg-slate-800 p-6 rounded-lg text-center">
                <p class="text-slate-600 dark:text-slate-400">Select a diagnostic option to get started</p>
            </div>
        @endif
    </div>
</div>
