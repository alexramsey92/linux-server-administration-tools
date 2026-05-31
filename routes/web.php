<?php

use App\Livewire\AI\Diagnostics;
use App\Livewire\Intranet\Index as IntranetIndex;
use App\Livewire\Inventory\HealthMonitor;
use App\Livewire\Inventory\ScannedIpTable;
use App\Livewire\Inventory\ServerDetail;
use App\Livewire\Inventory\ServerForm;
use App\Livewire\Inventory\ServerTable;
use App\Livewire\Inventory\SslCertificateForm;
use App\Livewire\Inventory\SslCertificateTable;
use App\Livewire\Inventory\VMScanner;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inventory')->name('home');

Route::get('/intranet', IntranetIndex::class)->name('intranet.index');

// Server Inventory Routes
Route::get('/inventory', ServerTable::class)->name('inventory.index');
Route::get('/inventory/scan', VMScanner::class)->name('inventory.scan');
Route::get('/inventory/scanned-ips', ScannedIpTable::class)->name('inventory.scanned-ips');
Route::get('/inventory/create', ServerForm::class)->name('inventory.create');
Route::get('/inventory/server/{server}/edit', ServerForm::class)->name('inventory.edit');
Route::get('/inventory/server/{server}', ServerDetail::class)->name('inventory.detail');
Route::get('/inventory/server/{server}/health', HealthMonitor::class)->name('inventory.health');
Route::get('/inventory/server/{server}/diagnostics', Diagnostics::class)->name('inventory.diagnostics');

// SSL Certificate Routes
Route::get('/inventory/ssl', SslCertificateTable::class)->name('inventory.ssl.index');
Route::get('/inventory/ssl/create', SslCertificateForm::class)->name('inventory.ssl.create');
Route::get('/inventory/ssl/{certificate}/edit', SslCertificateForm::class)->name('inventory.ssl.edit');
