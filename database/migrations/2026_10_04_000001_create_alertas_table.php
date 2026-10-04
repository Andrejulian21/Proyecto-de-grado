<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persistent alerts.
     *
     * Alerts used to be derived in the browser from two generic endpoints on
     * every mount, with no state and no way to dismiss them. Making them a real
     * table is what allows `reviewed_at` to exist at all.
     *
     * `clave` is the load-bearing column: it is the stable identity of an alert
     * (`bitacora_sin_firmar:12`, `entrega_vencida:34`, `firmas_sospechosas:7:2026-10-04-15`)
     * and the reason generation can be an idempotent upsert instead of an
     * append-only log that duplicates on every run.
     */
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table): void {
            $table->id();

            // Stable identity. Unique is what makes upsert-by-clave safe.
            $table->string('clave')->unique();

            $table->string('tipo', 50);
            $table->text('mensaje');

            // nullOnDelete: deleting a project takes its alerts with it instead
            // of leaving orphans for the coordinator to review against nothing.
            $table->foreignId('proyecto_id')
                ->nullable()
                ->constrained('proyectos')
                ->nullOnDelete();

            $table->string('severidad', 20);

            // Context needed to act without a second request: bitácora id,
            // entrega-pivot id, signature window, hours elapsed.
            $table->json('datos')->nullable();

            $table->timestamp('reviewed_at')->nullable();

            // nullOnDelete (not cascade): the alert must survive the reviewer
            // account. `audit_logs.user_id` is nullable for the same reason.
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Composite: the panel filters by tipo and unreviewed at once.
            $table->index(['tipo', 'reviewed_at'], 'alertas_tipo_reviewed_at_index');

            // Leading-column-alone: the KPI counts `WHERE reviewed_at IS NULL`
            // with no tipo predicate, so the composite cannot serve it.
            $table->index('reviewed_at', 'alertas_reviewed_at_index');

            // PostgreSQL does not index foreign keys automatically; this keeps
            // the nullOnDelete lookup on project removal cheap.
            $table->index('proyecto_id', 'alertas_proyecto_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
