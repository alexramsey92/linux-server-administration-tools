<?php

namespace App\Services\AI;

use Anthropic\Anthropic;
use App\Server;
use Exception;

class ServerDiagnostics
{
    private Anthropic $client;

    public function __construct()
    {
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            throw new Exception('Anthropic API key not configured');
        }

        $this->client = new Anthropic(['apiKey' => $apiKey]);
    }

    /**
     * Analyze server health and provide recommendations
     */
    public function analyzeHealth(Server $server): string
    {
        $metric = $server->latestMetric();
        if (! $metric) {
            return 'No metrics available for analysis.';
        }

        $prompt = $this->buildHealthAnalysisPrompt($server, $metric);

        return $this->callClaude($prompt);
    }

    /**
     * Diagnose a specific issue
     */
    public function diagnoseIssue(Server $server, string $issue): string
    {
        $metric = $server->latestMetric();

        $prompt = "You are a Linux system administrator expert. Diagnose the following issue on server '{$server->name}' ({$server->ip_address}):\n\n";
        $prompt .= "Issue: {$issue}\n\n";

        if ($metric) {
            $prompt .= "Current Metrics:\n";
            $prompt .= "- CPU Usage: {$metric->cpu_usage_percent}%\n";
            $prompt .= "- Memory Usage: {$metric->memory_usage_percent}%\n";
            $prompt .= "- Disk Usage: {$metric->disk_usage_percent}%\n";
            $prompt .= "- Load Average: {$metric->load_average_1}, {$metric->load_average_5}, {$metric->load_average_15}\n\n";
        }

        $prompt .= "Provide:\n";
        $prompt .= "1. Root cause analysis\n";
        $prompt .= "2. Recommended solutions\n";
        $prompt .= "3. Commands to run for diagnosis\n";

        return $this->callClaude($prompt);
    }

    /**
     * Get deployment recommendations
     */
    public function getDeploymentRecommendations(Server $server): string
    {
        $metric = $server->latestMetric();
        $appCount = $server->applications()->count();
        $serviceCount = $server->services()->count();

        $prompt = "You are a DevOps expert. Provide deployment recommendations for server '{$server->name}' based on:\n\n";
        $prompt .= "Server Specifications:\n";
        $prompt .= "- OS: {$server->os} {$server->os_version}\n";
        $prompt .= "- CPU Cores: {$server->cpu_cores}\n";
        $prompt .= "- RAM: {$server->ram_gb}GB\n";
        $prompt .= "- Disk: {$server->disk_gb}GB\n";
        $prompt .= "- Environment: {$server->environment}\n";
        $prompt .= "- Current Applications: {$appCount}\n";
        $prompt .= "- Current Services: {$serviceCount}\n";

        if ($metric) {
            $prompt .= "\nCurrent Resource Usage:\n";
            $prompt .= "- CPU: {$metric->cpu_usage_percent}%\n";
            $prompt .= "- Memory: {$metric->memory_usage_percent}%\n";
            $prompt .= "- Disk: {$metric->disk_usage_percent}%\n";
        }

        $prompt .= "\nProvide:\n";
        $prompt .= "1. Capacity assessment\n";
        $prompt .= "2. Optimization suggestions\n";
        $prompt .= "3. Scaling recommendations\n";

        return $this->callClaude($prompt);
    }

    private function buildHealthAnalysisPrompt(Server $server, $metric): string
    {
        $prompt = "You are a Linux systems administrator. Analyze the health of this server and provide actionable recommendations.\n\n";

        $prompt .= "Server Information:\n";
        $prompt .= "- Name: {$server->name}\n";
        $prompt .= "- IP: {$server->ip_address}\n";
        $prompt .= "- OS: {$server->os} {$server->os_version}\n";
        $prompt .= "- Status: {$server->status}\n\n";

        $prompt .= "Current Metrics:\n";
        $prompt .= "- CPU Usage: {$metric->cpu_usage_percent}%\n";
        $prompt .= "- Memory Usage: {$metric->memory_usage_percent}%\n";
        $prompt .= "- Disk Usage: {$metric->disk_usage_percent}%\n";
        $prompt .= "- Load Average (1/5/15): {$metric->load_average_1}/{$metric->load_average_5}/{$metric->load_average_15}\n";
        $prompt .= '- Uptime: '.floor($metric->uptime_seconds / 86400)." days\n\n";

        $prompt .= "Provide:\n";
        $prompt .= "1. Health summary (critical/warning/healthy)\n";
        $prompt .= "2. Key concerns (if any)\n";
        $prompt .= "3. Recommended actions\n";
        $prompt .= "4. Linux commands to investigate further\n";

        return $prompt;
    }

    private function callClaude(string $prompt): string
    {
        try {
            $message = $this->client->messages->create([
                'model' => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            return $message->content[0]->text ?? 'No response received';
        } catch (Exception $e) {
            return "Error calling Claude API: {$e->getMessage()}";
        }
    }
}
