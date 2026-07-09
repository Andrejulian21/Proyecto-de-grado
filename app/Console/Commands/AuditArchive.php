<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditArchive extends Command
{
    protected $signature = 'audit:archive
        {--dry-run : Count rows to archive without moving them}
        {--years=5 : Archive entries older than this many years}';

    protected $description = 'Archive audit log entries older than N years to audit_logs_archive';

    public function handle(): int
    {
        if (! Schema::hasTable('audit_logs_archive')) {
            $this->error('audit_logs_archive table does not exist. Run migrations first.');
            return Command::FAILURE;
        }

        $cutoff = now()->subYears((int) $this->option('years'));
        $dryRun = (bool) $this->option('dry-run');

        $count = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            $this->info('No entries to archive.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("{$count} entries would be archived (dry-run).");
            return Command::SUCCESS;
        }

        $this->info("Archiving {$count} entries older than {$cutoff->toDateString()}...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunk(500, function ($logs) use ($bar) {
                DB::transaction(function () use ($logs) {
                    foreach ($logs as $log) {
                        DB::table('audit_logs_archive')->insert($log->toArray());
                        $log->delete();
                    }
                });
                $bar->advance($logs->count());
            });

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$count} entries archived.");

        return Command::SUCCESS;
    }
}
