<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoEntrega;
use App\Enums\EstadoFirma;
use App\Models\Anuncio;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\Proyecto;
use App\Models\RecursoInformativo;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $proyecto = Proyecto::where('title', 'like', '%Sistema de Gestion%')->first();
        if (! $proyecto) {
            $this->command->error('Ejecuta primero TestUsersSeeder.');
            return;
        }

        $student = User::where('email', 'nicorfire1.4@gmail.com')->first();
        $director = User::where('email', 'julian21arteaga@gmail.com')->first();
        $coordinador = User::where('email', 'nmoreno534@unab.edu.co')->first();

        // ── Entregas con versiones ──
        $entregaAprobada = Entrega::updateOrCreate(
            ['proyecto_id' => $proyecto->id, 'phase' => 'anteproyecto'],
            [
                'title' => 'Documento de Anteproyecto',
                'description' => 'Definicion del problema, objetivos y metodologia propuesta.',
                'due_date' => '2026-03-15',
                'status' => EstadoEntrega::Aprobada,
                'consolidated_grade' => 4.5,
                'evaluation_complete' => true,
            ],
        );
        $this->createVersion($entregaAprobada, 1, 'anteproyecto_v1.pdf', 'pendiente');
        $this->createVersion($entregaAprobada, 2, 'anteproyecto_v2.pdf', 'aprobado');

        $entregaEnviada = Entrega::updateOrCreate(
            ['proyecto_id' => $proyecto->id, 'phase' => 'presentacion_anteproyecto'],
            [
                'title' => 'Presentacion de Anteproyecto',
                'description' => 'Diapositivas y material de sustentacion.',
                'due_date' => '2026-04-10',
                'status' => EstadoEntrega::Enviada,
            ],
        );
        $this->createVersion($entregaEnviada, 1, 'presentacion_anteproyecto_v1.pdf', 'pendiente');

        Entrega::updateOrCreate(
            ['proyecto_id' => $proyecto->id, 'phase' => 'desarrollo'],
            [
                'title' => 'Informe de Avance 1',
                'description' => 'Avance del desarrollo del proyecto.',
                'due_date' => '2026-06-15',
                'status' => EstadoEntrega::Pendiente,
            ],
        );

        Entrega::updateOrCreate(
            ['proyecto_id' => $proyecto->id, 'phase' => 'presentacion_final'],
            [
                'title' => 'Informe Final',
                'description' => 'Documento final del proyecto de grado.',
                'due_date' => '2026-08-15',
                'status' => EstadoEntrega::Creada,
            ],
        );

        // ── Bitacoras con estados de firma ──
        Bitacora::updateOrCreate(
            ['topic' => 'Definicion del alcance del proyecto'],
            [
                'proyecto_id' => $proyecto->id,
                'topic' => 'Definicion del alcance del proyecto',
                'notes' => 'Se definieron los limites del sistema y los modulos principales a desarrollar. Se acordaron las tecnologias a utilizar: Laravel para backend, React para frontend y PostgreSQL como base de datos.',
                'meeting_date' => '2026-03-10',
                'duration_hours' => 1.5,
                'signature_status' => EstadoFirma::Completada,
                'student_signed_at' => now()->subDays(60),
                'director_signed_at' => now()->subDays(58),
            ],
        );
        Bitacora::updateOrCreate(
            ['topic' => 'Analisis de requisitos funcionales'],
            [
                'proyecto_id' => $proyecto->id,
                'topic' => 'Analisis de requisitos funcionales',
                'notes' => 'Se listaron y priorizaron los requisitos funcionales del sistema. Se definieron los casos de uso principales para los roles de coordinador, director y estudiante.',
                'meeting_date' => '2026-03-24',
                'duration_hours' => 2,
                'signature_status' => EstadoFirma::FirmadaEstudiante,
                'student_signed_at' => now()->subDays(45),
            ],
        );
        Bitacora::updateOrCreate(
            ['topic' => 'Diseño de la interfaz de usuario'],
            [
                'proyecto_id' => $proyecto->id,
                'topic' => 'Diseno de la interfaz de usuario',
                'notes' => 'Se revisaron los wireframes propuestos y se definieron los componentes principales de la interfaz.',
                'meeting_date' => '2026-04-07',
                'duration_hours' => 1.5,
                'signature_status' => EstadoFirma::Pendiente,
            ],
        );

        // ── Anuncios ──
        $anuncios = [
            ['Inicio de semestre 2026-1', 'Se informa a todos los estudiantes que el semestre academico 2026-1 ha iniciado. Las fechas de entrega de anteproyectos estan publicadas en el sistema.'],
            ['Recordatorio fechas de entrega', 'Recordamos a todos los estudiantes que la fecha limite para la entrega de anteproyectos es el 15 de marzo. Revisar el calendario academico para mas detalles.'],
            ['Nuevos directores disponibles', 'Se han incorporado nuevos directores de proyecto al sistema. Los coordinadores pueden asignarlos desde el modulo de gestion.']
        ];
        foreach ($anuncios as $i => [$t, $c]) {
            Anuncio::updateOrCreate(
                ['title' => $t],
                [
                    'author_id' => $coordinador?->id ?? 1,
                    'content' => $c,
                    'is_active' => true,
                    'published_at' => now()->subDays(30 - $i * 5),
                ],
            );
        }

        // ── Recursos ──
        $recursos = [
            ['Guia para elaboracion de anteproyecto', 'documento', 'Guia completa con la estructura y requisitos para la elaboracion del documento de anteproyecto de grado.', null],
            ['Formato de bitacora oficial', 'formato', 'Formato oficial para el registro de bitacoras de proyecto de grado.', null],
            ['Normas APA septima edicion', 'enlace', 'Resumen de las normas APA septima edicion para la presentacion de trabajos academicos.', 'https://normasapa.com/'],
        ];
        foreach ($recursos as [$t, $cat, $desc, $link]) {
            RecursoInformativo::updateOrCreate(
                ['title' => $t],
                [
                    'author_id' => $coordinador?->id ?? 1,
                    'category' => $cat,
                    'description' => $desc,
                    'link' => $link,
                ],
            );
        }

        $this->command->info('Demo data seeded: entregas, bitacoras, anuncios, recursos.');
    }

    private function createVersion(Entrega $entrega, int $num, string $file, string $status): void
    {
        VersionDocumento::updateOrCreate(
            ['entrega_id' => $entrega->id, 'version_number' => $num],
            [
                'file_path' => "entregas/{$entrega->id}/v{$num}/{$file}",
                'file_size' => rand(500, 3000),
                'original_name' => $file,
                'uploaded_at' => now()->subDays(10 * $num),
            ],
        );
    }
}
