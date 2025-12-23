<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClientErrorReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckObservabilityAlerts extends Command
{
    protected $signature = 'observability:check';

    protected $description = 'Check audit and client error volume and emit alerts when thresholds are exceeded.';

    public function handle(): int
    {
        if (! config('observability.alerts.enabled', true)) {
            $this->info('Observability alerts disabled.');

            return self::SUCCESS;
        }

        $channel = (string) config('observability.alerts.log_channel', 'stack');
        $cooldown = (int) config('observability.alerts.cooldown_minutes', 30);

        $this->checkClientErrors($channel, $cooldown);
        $this->checkAuditLogs($channel, $cooldown);

        return self::SUCCESS;
    }

    private function checkClientErrors(string $channel, int $cooldown): void
    {
        $threshold = (int) config('observability.alerts.client_errors.threshold', 20);
        $window = (int) config('observability.alerts.client_errors.window_minutes', 5);

        if ($threshold <= 0 || $window <= 0) {
            return;
        }

        $since = now()->subMinutes($window);
        $count = ClientErrorReport::query()
            ->where('created_at', '>=', $since)
            ->count();

        $this->info(sprintf('Client errors in last %d minutes: %d', $window, $count));

        $this->maybeAlert('client_errors', $count, $threshold, $window, $channel, $cooldown);
    }

    private function checkAuditLogs(string $channel, int $cooldown): void
    {
        $threshold = (int) config('observability.alerts.audit.threshold', 100);
        $window = (int) config('observability.alerts.audit.window_minutes', 10);

        if ($threshold <= 0 || $window <= 0) {
            return;
        }

        $since = now()->subMinutes($window);
        $count = AuditLog::query()
            ->where('created_at', '>=', $since)
            ->count();

        $this->info(sprintf('Audit logs in last %d minutes: %d', $window, $count));

        $this->maybeAlert('audit_logs', $count, $threshold, $window, $channel, $cooldown);
    }

    private function maybeAlert(string $key, int $count, int $threshold, int $window, string $channel, int $cooldown): void
    {
        if ($count < $threshold) {
            return;
        }

        $cacheKey = "observability:alert:{$key}";
        if (! Cache::add($cacheKey, now()->toIso8601String(), now()->addMinutes($cooldown))) {
            return;
        }

        Log::channel($channel)->warning("observability.{$key}.spike", [
            'count' => $count,
            'threshold' => $threshold,
            'window_minutes' => $window,
        ]);
    }
}
