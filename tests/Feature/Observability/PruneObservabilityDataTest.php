<?php

use App\Models\AuditLog;
use App\Models\ClientErrorReport;

test('observability prune removes stale audit logs and client errors', function () {
    config()->set('observability.retention.audit_days', 7);
    config()->set('observability.retention.client_error_days', 3);

    $oldAudit = AuditLog::create([
        'action' => 'test.audit',
    ]);
    AuditLog::query()->whereKey($oldAudit->id)->update([
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);

    $freshAudit = AuditLog::create([
        'action' => 'test.audit',
    ]);
    AuditLog::query()->whereKey($freshAudit->id)->update([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $oldClient = ClientErrorReport::create([
        'severity' => 'error',
        'message' => 'Old client error',
        'url' => 'https://example.test/old',
    ]);
    ClientErrorReport::query()->whereKey($oldClient->id)->update([
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ]);

    $freshClient = ClientErrorReport::create([
        'severity' => 'error',
        'message' => 'Fresh client error',
        'url' => 'https://example.test/fresh',
    ]);
    ClientErrorReport::query()->whereKey($freshClient->id)->update([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->artisan('observability:prune')
        ->assertExitCode(0);

    $this->assertDatabaseMissing('audit_logs', ['id' => $oldAudit->id]);
    $this->assertDatabaseHas('audit_logs', ['id' => $freshAudit->id]);
    $this->assertDatabaseMissing('client_error_reports', ['id' => $oldClient->id]);
    $this->assertDatabaseHas('client_error_reports', ['id' => $freshClient->id]);
});
