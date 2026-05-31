<?php

namespace App\Livewire\AI;

use App\Server;
use App\Services\AI\ServerDiagnostics;
use Livewire\Component;

class Diagnostics extends Component
{
    public Server $server;

    public string $selectedDiagnostic = 'health';

    public string $diagnosis = '';

    public bool $isLoading = false;

    public ?string $customIssue = null;

    public function mount(Server $server): void
    {
        $this->server = $server;
    }

    public function runDiagnostic(string $type): void
    {
        $this->isLoading = true;
        $this->diagnosis = '';

        try {
            $diagnostics = new ServerDiagnostics;

            $this->diagnosis = match ($type) {
                'health' => $diagnostics->analyzeHealth($this->server),
                'deployment' => $diagnostics->getDeploymentRecommendations($this->server),
                'issue' => $this->customIssue
                    ? $diagnostics->diagnoseIssue($this->server, $this->customIssue)
                    : 'Please enter an issue description',
                default => 'Unknown diagnostic type',
            };

            $this->selectedDiagnostic = $type;
        } catch (\Exception $e) {
            $this->addError('diagnosis', "Failed to run diagnostic: {$e->getMessage()}");
        } finally {
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.a-i.diagnostics');
    }
}
