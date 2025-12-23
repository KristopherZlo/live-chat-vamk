<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClientErrorReport;
use Illuminate\Console\Command;

class PruneObservabilityData extends Command
{
    protected $signature = 'observability:prune {--dry-run : Report counts without deleting}';

    protected $description = 'Prune audit logs and client error reports based on retention settings.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $auditDays = (int) config('observability.retention.audit_days', 180);
        $clientDays = (int) config('observability.retention.client_error_days', 30);

        $auditDeleted = $this->pruneAuditLogs($auditDays, $dryRun);
        $clientDeleted = $this->pruneClientErrors($clientDays, $dryRun);

        $this->info(sprintf('Audit logs deleted: %d', $auditDeleted));
        $this->info(sprintf('Client error reports deleted: %d', $clientDeleted));

        return self::SUCCESS;
    }

    private function pruneAuditLogs(int $days, bool $dryRun): int
    {
        if ($days <= 0) {
            $this->warn('Audit log retention disabled.');

            return 0;
        }

        $cutoff = now()->subDays($days);
        $query = AuditLog::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($dryRun) {
            $this->info(sprintf('Audit logs eligible for deletion: %d', $count));

            return 0;
        }

        return $count === 0 ? 0 : $query->delete();
    }

    private function pruneClientErrors(int $days, bool $dryRun): int
    {
        if ($days <= 0) {
            $this->warn('Client error retention disabled.');

            return 0;
        }

        $cutoff = now()->subDays($days);
        $query = ClientErrorReport::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($dryRun) {
            $this->info(sprintf('Client error reports eligible for deletion: %d', $count));

            return 0;
        }

        return $count === 0 ? 0 : $query->delete();
    }
}
