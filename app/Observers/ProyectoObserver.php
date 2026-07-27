<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Entrega;
use App\Models\Proyecto;
use Illuminate\Support\Facades\DB;

class ProyectoObserver
{
    /**
     * Handle the Proyecto "created" event.
     *
     * Vincular nuevas entregas existentes del semestre al proyecto recién creado.
     */
    public function created(Proyecto $proyecto): void
    {
        $entregas = Entrega::where('semester_id', $proyecto->semester_id)->get();

        foreach ($entregas as $entrega) {
            $exists = DB::table('entrega_proyecto')
                ->where('entrega_id', $entrega->id)
                ->where('proyecto_id', $proyecto->id)
                ->exists();

            if (! $exists) {
                DB::table('entrega_proyecto')->insert([
                    'entrega_id' => $entrega->id,
                    'proyecto_id' => $proyecto->id,
                ]);
            }
        }
    }
}
