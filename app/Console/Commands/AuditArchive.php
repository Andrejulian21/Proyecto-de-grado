<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditArchive extends Command
{
    protected $signature = 'audit:archive
        {--dry-run : Count rows to archive without moving them}
        {--years=5 : Archive entries older than this many years}';

    protected $description = 'Archive audit log entries older than N years to audit_logs_archive';

    /**
     * Run the archive operation.
     *
     * Why query builder, not the Eloquent model: the AuditLog model
     * blocks save() on existing rows and delete() unconditionally
     * (append-only by design). For the archive command we MUST be
     * able to move rows out of `audit_logs`, so we use
     * `DB::table('audit_logs')` for both read and delete. On
     * PostgreSQL the archive transaction issues
     * `SET LOCAL session_replication_role = replica` so the
     * `audit_logs_immutable` trigger (issue #12) does not block the
     * privileged maintenance delete; on SQLite the trigger does not
     * exist, so the `SET LOCAL` is skipped.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('audit_logs_archive')) {
            $this->error('audit_logs_archive table does not exist. Run migrations first.');

            return Command::FAILURE;
        }

        $cutoff = now()->subYears((int) $this->option('years'));
        $dryRun = (bool) $this->option('dry-run');
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        $count = (int) DB::table('audit_logs')
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

        DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($bar, $isPgsql) {
                $rows = $rows instanceof EloquentCollection ? $rows : EloquentCollection::make($rows);

                DB::transaction(function () use ($rows, $isPgsql) {
                    // Bypass the immutability trigger (issue #12) for
                    // the duration of this transaction. The DELETE
                    // below is the only place the trigger must be
                    // defeated, and `SET LOCAL` resets automatically
                    // at COMMIT.
                    if ($isPgsql) {
                        DB::statement('SET LOCAL session_replication_role = replica');
                    }

                    $payload = $rows->map(fn ($r) => (array) $r)->all();

                    if ($payload !== []) {
                        DB::table('audit_logs_archive')->insert($payload);
                        DB::table('audit_logs')
                            ->whereIn('id', $rows->pluck('id')->all())
                            ->delete();
                    }

                    if ($isPgsql) {
                        DB::statement('SET LOCAL session_replication_role = DEFAULT');
                    }
                });

                $bar->advance($rows->count());
            });

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$count} entries archived.");

        return Command::SUCCESS;
    }
}
