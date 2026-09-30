<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\Proyecto;
use App\Models\VersionDocumento;

/**
 * Builds document versions the way production does.
 *
 * EntregaEstudianteController::subirArchivoPorSlug always resolves (or
 * creates) the `entrega_proyecto` pivot BEFORE writing the VersionDocumento,
 * so a real uploaded version always carries `entrega_proyecto_id`. That
 * column is the isolation boundary between the projects sharing an entrega
 * template, and the FK is `ON DELETE SET NULL`.
 *
 * A fixture built with a bare `VersionDocumento::create()` leaves it null,
 * which describes a state the application cannot produce and which silently
 * defeats every per-project check (an ownerless document belongs to nobody,
 * so nothing can reach it). Use this factory whenever a version is meant to
 * belong to somebody.
 */
final class ProjectDeliveryVersion
{
    /**
     * Resolve the project delivery pivot for an entrega, creating it when the
     * link does not exist yet (same firstOrCreate the upload flow performs).
     */
    public static function pivotFor(Entrega $entrega, Proyecto $proyecto): EntregaProyecto
    {
        return EntregaProyecto::firstOrCreate(
            ['entrega_id' => $entrega->id, 'proyecto_id' => $proyecto->id],
            ['estado' => 'pendiente'],
        );
    }

    /**
     * Create a version owned by one project's delivery of a shared entrega.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(Entrega $entrega, Proyecto $proyecto, array $attributes = []): VersionDocumento
    {
        return VersionDocumento::create(array_merge([
            'entrega_id' => $entrega->id,
            'entrega_proyecto_id' => self::pivotFor($entrega, $proyecto)->id,
            'uploaded_at' => now(),
        ], $attributes));
    }
}
