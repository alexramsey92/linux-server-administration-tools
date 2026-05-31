<div class="space-y-6 p-6">
	<div class="space-y-1">
		<h1 class="text-3xl font-bold text-slate-900 dark:text-white">Intranet Dashboard</h1>
	</div>

	<section class="space-y-3">
		<h2 class="text-lg font-semibold text-slate-900 dark:text-white">Immediate Attention</h2>
		<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

			<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
				<div class="flex items-start justify-between gap-3">
					<p class="text-sm font-medium text-slate-600 dark:text-slate-400">SSL Expired</p>
					<span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">Critical</span>
				</div>
				<p class="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{{ $this->expiredCertificatesCount }}</p>
			</div>

			<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
				<div class="flex items-start justify-between gap-3">
					<p class="text-sm font-medium text-slate-600 dark:text-slate-400">SSL Expiring ≤ 30 Days</p>
					<span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Warning</span>
				</div>
				<p class="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{{ $this->expiringCertificatesCount }}</p>
			</div>

			<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
				<div class="flex items-start justify-between gap-3">
					<p class="text-sm font-medium text-slate-600 dark:text-slate-400">Unknown/Maintenance Status</p>
					<span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Warning</span>
				</div>
				<p class="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{{ $this->unknownStatusServersCount }}</p>
			</div>
		</div>
	</section>

	<section class="space-y-3">
		<h2 class="text-lg font-semibold text-slate-900 dark:text-white">External Systems</h2>
		<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
			<a
				href="https://www.servicenow.com"
				target="_blank"
				rel="noopener noreferrer"
				class="group rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-600"
			>
				<p class="text-base font-semibold text-slate-900 dark:text-white">Service Now</p>
				<p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Open ticketing and service workflows.</p>
			</a>

			<a
				href="https://www.splunk.com"
				target="_blank"
				rel="noopener noreferrer"
				class="group rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-600"
			>
				<p class="text-base font-semibold text-slate-900 dark:text-white">Splunk</p>
				<p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Review logs, alerts, and observability data.</p>
			</a>

			<a
				href="https://www.solarwinds.com"
				target="_blank"
				rel="noopener noreferrer"
				class="group rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-600"
			>
				<p class="text-base font-semibold text-slate-900 dark:text-white">SolarWinds</p>
				<p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Access network and infrastructure monitoring.</p>
			</a>


		</div>
	</section>


	<section class="space-y-3">
		<h2 class="text-lg font-semibold text-slate-900 dark:text-white">Internal Links</h2>
		<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">

			<a
                href="https://intranet.example.com"
                target="_blank"
                rel="noopener noreferrer"
                class="group rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-600"
            >
                <p class="text-base font-semibold text-slate-900 dark:text-white">Intranet Portal</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Open the internal portal.</p>
            </a>

            <a
                href="https://status.example.com"
                target="_blank"
                rel="noopener noreferrer"
                class="group rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-600"
            >
                <p class="text-base font-semibold text-slate-900 dark:text-white">Status Portal</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Open the system status dashboard.</p>
            </a>


		</div>
	</section>
</div>
