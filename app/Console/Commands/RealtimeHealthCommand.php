<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RealtimeHealthCommand extends Command
{
    protected $signature = 'realtime:health
                            {--json : Output health metrics as JSON}
                            {--no-connect : Skip live TCP probe to Reverb server}';

    protected $description = 'Inspect WebSocket (Reverb), database queue backlog, and NING webhook configuration health';

    public function handle(): int
    {
        $broadcastDriver = (string) config('broadcasting.default', 'null');
        $queueDriver = (string) config('queue.default', 'sync');
        $reverbHost = (string) (config('broadcasting.connections.reverb.options.host') ?: '127.0.0.1');
        $reverbPort = (int) (config('broadcasting.connections.reverb.options.port') ?: 8080);
        $reverbScheme = (string) (config('broadcasting.connections.reverb.options.scheme') ?: 'http');
        $reverbConfigured = $broadcastDriver === 'reverb' && ! empty(config('broadcasting.connections.reverb.key'));
        $ningConfigured = ! empty(config('services.ning.webhook_secret'));

        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        $tcpReachable = null;
        if (! $this->option('no-connect') && $broadcastDriver === 'reverb') {
            $probeHost = (string) config('reverb.servers.reverb.host', '127.0.0.1');
            if ($probeHost === '0.0.0.0') {
                $probeHost = '127.0.0.1';
            }
            $probePort = (int) config('reverb.servers.reverb.port', 8080);
            $socket = @fsockopen($probeHost, $probePort, $errno, $errstr, 2);
            $tcpReachable = is_resource($socket);
            if (is_resource($socket)) {
                fclose($socket);
            }
        }

        $healthy = $tcpReachable !== false;

        $report = [
            'healthy' => $healthy,
            'broadcast_driver' => $broadcastDriver,
            'queue_driver' => $queueDriver,
            'reverb_configured' => $reverbConfigured,
            'reverb_endpoint' => "{$reverbScheme}://{$reverbHost}:{$reverbPort}",
            'reverb_tcp_reachable' => $tcpReachable,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'ning_webhook_configured' => $ningConfigured,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Broadcast Driver', $broadcastDriver],
                ['Queue Driver', $queueDriver],
                ['Reverb Configured', $reverbConfigured ? 'Yes' : 'No'],
                ['Reverb Endpoint', $report['reverb_endpoint']],
                ['Reverb TCP Reachable', $tcpReachable === null ? 'Skipped' : ($tcpReachable ? 'OK' : 'Unreachable')],
                ['Pending Queue Jobs', (string) $pendingJobs],
                ['Failed Queue Jobs', (string) $failedJobs],
                ['NING Webhook Secret', $ningConfigured ? 'Configured' : 'Not Configured'],
            ],
        );

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
