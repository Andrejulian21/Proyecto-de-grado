<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate legacy entregas from the old format (one per proyecto_id) to the new
     * format (one per grupo/semester with pivot entries).
     *
     * Steps:
     * 1. Derive semester_id from proyecto_id for any entregas that don't have it.
     * 2. Group entregas by (semester_id, phase, title) and keep the one with lowest ID.
     * 3. Create missing entrega_proyecto pivot entries.
     * 4. Migrate versiones to use entrega_proyecto_id and archivo_requerido_id.
     * 5. Assign default archivos_requeridos to any entrega that has none.
     * 6. Delete duplicate entregas (those merged into the kept one).
     */
    public function up(): void
    {
        // Step 0: Ensure semester_id is set for every entrega by deriving
        // it from proyecto_id when missing.
        $this->ensureSemesterId();

        // Step 1-2: Group by (semester_id, phase, title), keep minimum ID.
        $groups = DB::table('entregas')
            ->select('semester_id', 'phase', 'title', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('semester_id')
            ->groupBy('semester_id', 'phase', 'title')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $keepId = (int) $group->keep_id;

            // Find all duplicate IDs (same group, different ID).
            $duplicates = DB::table('entregas')
                ->where('semester_id', $group->semester_id)
                ->where('phase', $group->phase)
                ->where('title', $group->title)
                ->where('id', '!=', $keepId)
                ->pluck('id');

            // Step 3: Create pivot entries from the duplicate's proyecto_id.
            foreach ($duplicates as $dupId) {
                $dup = DB::table('entregas')->find($dupId);
                if ($dup !== null && $dup->proyecto_id !== null) {
                    DB::table('entrega_proyecto')->insertOrIgnore([
                        'entrega_id' => $keepId,
                        'proyecto_id' => $dup->proyecto_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Step 4: Re-point versiones from duplicates to the kept entrega.
            DB::table('versiones_documento')
                ->whereIn('entrega_id', $duplicates)
                ->update(['entrega_id' => $keepId]);

            // Step 6: Delete duplicate entregas.
            DB::table('entregas')->whereIn('id', $duplicates)->delete();
        }

        // Step 3b: For non-duplicate entregas with proyecto_id but no pivot,
        // create the pivot entry.
        DB::table('entregas')
            ->whereNotNull('proyecto_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('entrega_proyecto')
                    ->whereColumn('entrega_proyecto.entrega_id', 'entregas.id');
            })
            ->orderBy('id')
            ->each(function ($entrega) {
                DB::table('entrega_proyecto')->insertOrIgnore([
                    'entrega_id' => $entrega->id,
                    'proyecto_id' => $entrega->proyecto_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // Step 5: Assign default archivos_requeridos to any that lack them.
        $defaultArchivos = json_encode([
            ['slug' => 'documento', 'nombre' => 'Documento', 'versionamiento' => true],
        ]);

        DB::table('entregas')
            ->whereNull('archivos_requeridos')
            ->update(['archivos_requeridos' => $defaultArchivos]);

        // Step 4b: Migrate existing versiones that lack entrega_proyecto_id
        // and archivo_requerido_id. We only handle the first pivot found,
        // since each entrega should have at least one pivot.
        DB::table('versiones_documento')
            ->whereNull('entrega_proyecto_id')
            ->orderBy('id')
            ->each(function ($version) {
                $pivot = DB::table('entrega_proyecto')
                    ->where('entrega_id', $version->entrega_id)
                    ->first();

                if ($pivot !== null) {
                    DB::table('versiones_documento')
                        ->where('id', $version->id)
                        ->update([
                            'entrega_proyecto_id' => $pivot->id,
                            'archivo_requerido_id' => 'documento', // default slug
                        ]);
                }
            });
    }

    /**
     * Reverse the migration.
     *
     * This is a DATA migration — there is no clean reverse.
     * Deleted entregas cannot be restored. We mark this as
     * irreversible but provide a no-op down so the migration
     * system is aware of the intent.
     */
    public function down(): void
    {
        // Irreversible: deleted entregas cannot be recovered.
        // Do NOT add reverse logic here.
    }

    /**
     * Ensure every entrega has a semester_id by deriving it from its proyecto.
     */
    private function ensureSemesterId(): void
    {
        DB::statement('
            UPDATE entregas
            SET semester_id = (
                SELECT proyectos.semester_id
                FROM proyectos
                WHERE proyectos.id = entregas.proyecto_id
            )
            WHERE semester_id IS NULL
              AND proyecto_id IS NOT NULL
        ');
    }
};
