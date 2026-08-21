<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DirectorAcademicProfileController;
use App\Http\Controllers\Admin\DirectorCupoController;
use App\Http\Controllers\Admin\EntregaController;
use App\Http\Controllers\Admin\EvaluadorProyectoController;
use App\Http\Controllers\Admin\ProyectoController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\SeguimientoController;
use App\Http\Controllers\Admin\SemestreController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\AnuncioController;
use App\Http\Controllers\Api\AsistenteAcademicoController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\ConsultaNotasController;
use App\Http\Controllers\Api\DirectorController;
use App\Http\Controllers\Api\EntregaEstudianteController;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\EvaluacionAbetController;
use App\Http\Controllers\Api\EvaluacionController;
use App\Http\Controllers\Api\EvaluacionInteligenteController;
use App\Http\Controllers\Api\EvaluadorAsignacionesController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\RecursoController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with `/api` and run through the `api`
| middleware group (Sanctum stateful, throttle, etc).
|
| PR 2 — Auth Backend (T-014 to T-025):
|   - POST   /api/auth/externo/login      → loginExterno        (T-016)
|   - POST   /api/auth/change-password    → changePassword      (T-017)
|   - POST   /api/auth/logout             → logout              (T-023)
|   - GET    /api/auth/user               → sessionCheck        (T-023)
|   - GET    /api/admin/audit-logs        → AuditLogController  (T-024)
|   - CRUD   /api/admin/whitelist         → UserController      (T-020)
|   - POST   /api/admin/evaluadores       → UserController      (T-017)
|
*/

// Health check (used by `/up` and uptime monitors).
Route::get('/health', fn () => ['status' => 'ok', 'time' => now()->toIso8601String()]);

// -- guest routes ----------------------------------------------------

Route::post('/auth/externo/login', [AuthController::class, 'loginExterno'])
    ->middleware('throttle:login')
    ->name('auth.externo.login');

// -- authenticated routes -------------------------------------------

// Authenticated API routes are guarded by:
//   - auth:sanctum           (Sanctum cookie/bearer token)
//   - single_session         (T-021, belt-and-suspenders)
//   - activity               (T-022, 8h inactivity timeout)
//   - ensure_password_changed (T-017, forces external evaluators to
//     change their temporary password)
Route::middleware([
    'auth:sanctum',
    'single_session',
    'activity',
])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'sessionCheck'])
        ->name('auth.user');

    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])
        ->name('auth.change_password');

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');

    // Bitácoras CRUD + firma (T-012, PR 1 firma por clave dinámica).
    Route::get('/bitacoras', [BitacoraController::class, 'index'])
        ->name('bitacoras.index');
    Route::post('/bitacoras', [BitacoraController::class, 'store'])
        ->name('bitacoras.store');
    Route::get('/bitacoras/{id}', [BitacoraController::class, 'show'])
        ->whereNumber('id')
        ->name('bitacoras.show');
    Route::put('/bitacoras/{id}', [BitacoraController::class, 'update'])
        ->whereNumber('id')
        ->name('bitacoras.update');
    Route::post('/bitacoras/{id}/firmar', [BitacoraController::class, 'firmar'])
        ->whereNumber('id')
        ->name('bitacoras.firmar');
    Route::post('/bitacoras/{id}/re-solicitar-codigo', [BitacoraController::class, 'reSolicitarCodigo'])
        ->whereNumber('id')
        ->name('bitacoras.re_solicitar_codigo');

    // T-014: Total bitácora hours per project (director/coordinador)
    Route::get('/director/proyectos/{id}/horas', [BitacoraController::class, 'horas'])
        ->whereNumber('id')
        ->name('director.proyectos.horas');

    // Estudiante — proyecto y entregas propias
    Route::get('/estudiante/proyecto', [EstudianteController::class, 'proyecto'])
        ->name('estudiante.proyecto');
    Route::put('/estudiante/proyecto', [EstudianteController::class, 'actualizarProyecto'])
        ->name('estudiante.proyecto.update');
    Route::get('/estudiante/entregas', [EstudianteController::class, 'entregas'])
        ->name('estudiante.entregas');
    Route::get('/notas', [ConsultaNotasController::class, 'index'])
        ->middleware('role:Coordinador,Director,Estudiante,EvaluadorExterno')
        ->name('notas.index');
    Route::get('/estudiante/entregas/{entrega}/evaluacion-inteligente', [EvaluacionInteligenteController::class, 'index'])
        ->whereNumber('entrega')
        ->name('estudiante.entregas.evaluacion_inteligente.index');
    Route::post('/estudiante/entregas/{entrega}/evaluacion-inteligente', [EvaluacionInteligenteController::class, 'store'])
        ->whereNumber('entrega')
        ->name('estudiante.entregas.evaluacion_inteligente');
    Route::get('/estudiante/asistente/conversacion', [AsistenteAcademicoController::class, 'show'])
        ->name('estudiante.asistente.conversacion');
    Route::post('/estudiante/asistente/mensajes', [AsistenteAcademicoController::class, 'store'])
        ->name('estudiante.asistente.mensajes');

    // Estudiante — subir archivos por slug y consultar estado de completitud (PR 2)
    Route::post('/entregas/{entrega}/archivos/{slug}', [EntregaEstudianteController::class, 'subirArchivoPorSlug'])
        ->whereNumber('entrega')
        ->name('entregas.archivos.subir');
    Route::get('/entregas/{entrega}/estado', [EntregaEstudianteController::class, 'estadoCompletitud'])
        ->whereNumber('entrega')
        ->name('entregas.estado');

    // Entregas — versiones (accessible by authenticated students and directors)
    Route::get('/entregas/{id}/versiones', [EntregaController::class, 'versiones'])
        ->whereNumber('id')
        ->name('entregas.versiones');
    // ELIMINADO (PR 2): Route::post('/entregas/{id}/versiones', [EntregaController::class, 'subirVersion'])
    //     ->whereNumber('id')
    //     ->name('entregas.subir_version');
    Route::delete('/entregas/{entregaId}/versiones/{versionId}', [EntregaController::class, 'eliminarVersion'])
        ->whereNumber('entregaId')
        ->whereNumber('versionId')
        ->name('entregas.eliminar_version');

    // Estudiante solicita habilitación para subir versiones
    Route::post('/entregas/{id}/solicitar', [EntregaController::class, 'solicitar'])
        ->whereNumber('id')
        ->name('entregas.solicitar');

    // Evaluaciones (T-016).
    Route::get('/evaluaciones', [EvaluacionController::class, 'index'])
        ->name('evaluaciones.index');
    Route::post('/evaluaciones', [EvaluacionController::class, 'store'])
        ->name('evaluaciones.store');

    // Consolidado por entrega (T-017).
    Route::get('/evaluaciones/{entrega_id}/consolidado', [EvaluacionController::class, 'consolidado'])
        ->whereNumber('entrega_id')
        ->name('evaluaciones.consolidado');

    // Anuncios — todos los roles pueden ver (T-020)
    Route::get('/anuncios', [AnuncioController::class, 'index'])
        ->name('anuncios.index');
    Route::get('/anuncios/{anuncio}', [AnuncioController::class, 'show'])
        ->name('anuncios.show');

    // Recursos — todos los roles pueden ver (T-021)
    Route::get('/recursos', [RecursoController::class, 'index'])
        ->name('recursos.index');
    Route::get('/recursos/{recurso}', [RecursoController::class, 'show'])
        ->whereNumber('recurso')
        ->name('recursos.show');

    // Notificaciones (T-022)
    Route::get('/notificaciones', [NotificacionController::class, 'index'])
        ->name('notificaciones.index');
    Route::get('/notificaciones/no-leidas', [NotificacionController::class, 'noLeidas'])
        ->name('notificaciones.no-leidas');
    Route::put('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'marcarLeida'])
        ->name('notificaciones.leer');

    // Director dashboard endpoints
    Route::prefix('director')->name('director.')->group(function () {
        Route::get('/proyectos', [DirectorController::class, 'proyectos']);
        Route::get('/proyectos/{id}/bitacoras', [DirectorController::class, 'bitacoras'])
            ->whereNumber('id');
        Route::get('/proyectos/{id}', [DirectorController::class, 'proyectoDetalle'])
            ->whereNumber('id');
        Route::get('/kpis', [DirectorController::class, 'kpis']);
        Route::get('/entregas', [DirectorController::class, 'entregas']);
        // PR 3 — Evaluaciones
        Route::get('/evaluaciones', [DirectorController::class, 'evaluaciones']);
        Route::get('/proyectos/{proyecto}/entrega-fase', [DirectorController::class, 'entregaFase'])
            ->whereNumber('proyecto');
        Route::get('/entregas/{entrega}/evaluacion-abet', [EvaluacionAbetController::class, 'show'])
            ->whereNumber('entrega')
            ->name('entregas.evaluacion_abet.show');
        Route::post('/entregas/{entrega}/evaluacion-abet', [EvaluacionAbetController::class, 'store'])
            ->whereNumber('entrega')
            ->name('entregas.evaluacion_abet');

        // PR 1 — Cartas de aval (solo director)
        Route::middleware('role:Director')->group(function () {
            Route::get('/cartas/proyectos', [DirectorController::class, 'cartasProyectos'])
                ->name('cartas.proyectos');
            Route::get('/cartas/{proyecto}/estudiante/{user}/aval-sustentacion', [DirectorController::class, 'generarAvalSustentacion'])
                ->whereNumber('proyecto')
                ->whereNumber('user')
                ->name('cartas.aval-sustentacion');
            Route::get('/cartas/{proyecto}/estudiante/{user}/carta-jurados', [DirectorController::class, 'generarCartaJurados'])
                ->whereNumber('proyecto')
                ->whereNumber('user')
                ->name('cartas.carta-jurados');
        });
    });

    // Evaluador — asignaciones y evaluación (PR 3, T-017..T-019).
    Route::prefix('evaluador')->name('evaluador.')->group(function () {
        Route::get('/mis-asignaciones', [EvaluadorAsignacionesController::class, 'index'])
            ->name('mis-asignaciones');
        Route::get('/asignaciones/{id}/detalle', [EvaluadorAsignacionesController::class, 'detalle'])
            ->whereNumber('id')
            ->name('asignaciones.detalle');
        Route::post('/asignaciones/{id}/evaluar', [EvaluadorAsignacionesController::class, 'evaluar'])
            ->whereNumber('id')
            ->name('asignaciones.evaluar');
    });
});

// -- admin (coordinador-only) routes ---------------------------------

Route::middleware(['auth:sanctum', 'single_session', 'activity', 'role:Coordinador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/usuarios', [UserController::class, 'usuarios'])
            ->name('usuarios.index');
        Route::put('/usuarios/{user}', [UserController::class, 'updateUsuario'])
            ->name('usuarios.update');
        Route::put('/usuarios/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('usuarios.reset-password');
        Route::delete('/usuarios/{user}', [UserController::class, 'destroyUsuario'])
            ->name('usuarios.destroy');
        Route::post('/usuarios/{user}/eliminar-con-reasignacion', [UserController::class, 'destroyDirectorWithReassignment'])
            ->name('usuarios.destroy-with-reassignment');

        Route::post('/evaluadores', [UserController::class, 'storeExternal'])
            ->name('evaluadores.store');

        // Whitelist CRUD (T-020).
        Route::get('/whitelist', [UserController::class, 'index'])
            ->name('whitelist.index');
        Route::post('/whitelist', [UserController::class, 'store'])
            ->name('whitelist.store');
        Route::put('/whitelist/{id}', [UserController::class, 'update'])
            ->whereNumber('id')
            ->name('whitelist.update');
        Route::delete('/whitelist/{id}', [UserController::class, 'destroy'])
            ->whereNumber('id')
            ->name('whitelist.destroy');

        // Audit log viewer (T-024).
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');

        // Proyectos KPIs (T-006). Must be before apiResource to avoid wildcard collision.
        Route::get('/proyectos/kpis', [ProyectoController::class, 'kpis'])
            ->name('proyectos.kpis');

        // Proyectos CRUD (T-002).
        Route::apiResource('proyectos', ProyectoController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        // Semestres CRUD (T-001).
        Route::apiResource('semestres', SemestreController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Evaluador-proyecto asignación (T-016).
        Route::get('/evaluador-proyecto', [EvaluadorProyectoController::class, 'index'])
            ->name('evaluador-proyecto.index');
        Route::post('/evaluador-proyecto', [EvaluadorProyectoController::class, 'store'])
            ->name('evaluador-proyecto.store');
        Route::put('/evaluador-proyecto/{id}', [EvaluadorProyectoController::class, 'update'])
            ->whereNumber('id')
            ->name('evaluador-proyecto.update');
        Route::delete('/evaluador-proyecto/{id}', [EvaluadorProyectoController::class, 'destroy'])
            ->whereNumber('id')
            ->name('evaluador-proyecto.destroy');

        // Reporte consolidado (T-018).
        Route::get('/reportes/consolidado', [ReporteController::class, 'consolidado'])
            ->name('reportes.consolidado');

        // Anuncios CRUD (T-020) — solo coordinador
        Route::post('/anuncios', [AnuncioController::class, 'store'])
            ->name('anuncios.store');
        Route::put('/anuncios/{anuncio}', [AnuncioController::class, 'update'])
            ->name('anuncios.update');
        Route::delete('/anuncios/{anuncio}', [AnuncioController::class, 'destroy'])
            ->name('anuncios.destroy');

        // Recursos CRUD (T-021) — solo coordinador
        Route::get('/recursos', [RecursoController::class, 'index'])
            ->name('recursos.admin');
        Route::post('/recursos', [RecursoController::class, 'store'])
            ->name('recursos.store');
        Route::put('/recursos/{recurso}', [RecursoController::class, 'update'])
            ->name('recursos.update');
        Route::delete('/recursos/{recurso}', [RecursoController::class, 'destroy'])
            ->name('recursos.destroy');

        // Director quota management — cupos (Sprint 5).
        Route::get('/directores/cupos', [DirectorCupoController::class, 'index'])
            ->name('directores.cupos');
        Route::put('/directores/{director}/cupo', [DirectorCupoController::class, 'update'])
            ->whereNumber('director')
            ->name('directores.cupo.update');
        Route::get('/directores/{director}/perfil-academico', [DirectorAcademicProfileController::class, 'show'])
            ->whereNumber('director')
            ->name('directores.perfil_academico.show');
        Route::put('/directores/{director}/perfil-academico', [DirectorAcademicProfileController::class, 'update'])
            ->whereNumber('director')
            ->name('directores.perfil_academico.update');

        // Directores list + sus proyectos (para la página /directores).
        Route::get('/directores', [DirectorCupoController::class, 'directores'])
            ->name('directores.index');
        Route::get('/directores/{director}/proyectos', [DirectorCupoController::class, 'directorProyectos'])
            ->whereNumber('director')
            ->name('directores.proyectos');

        // Bitácoras por proyecto (para la página /directores).
        Route::get('/proyectos/{proyecto}/bitacoras', [BitacoraController::class, 'porProyecto'])
            ->whereNumber('proyecto')
            ->name('proyectos.bitacoras');

        // PR 2 — Seguimiento (backend).
        Route::get('/seguimiento/semestre/{semestre}', [SeguimientoController::class, 'porSemestre'])
            ->whereNumber('semestre')
            ->name('seguimiento.por-semestre');
        Route::get('/seguimiento/semestre/{semestre}/export', [SeguimientoController::class, 'exportar'])
            ->whereNumber('semestre')
            ->name('seguimiento.export');
        Route::put('/seguimiento/observaciones', [SeguimientoController::class, 'guardarObservacion'])
            ->name('seguimiento.guardar-observacion');
    });

// Entregas — accessible by all authenticated roles (controller handles RBAC)
Route::middleware(['auth:sanctum', 'single_session', 'activity'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/entregas/finales', [EntregaController::class, 'finales'])
            ->name('entregas.finales');
        Route::get('/entregas', [EntregaController::class, 'index'])
            ->name('entregas.index');
        Route::get('/entregas/{id}', [EntregaController::class, 'show'])
            ->whereNumber('id')
            ->name('entregas.show');
        Route::post('/entregas', [EntregaController::class, 'store'])
            ->name('entregas.store');
        Route::put('/entregas/{id}/revisar', [EntregaController::class, 'revisar'])
            ->whereNumber('id')
            ->name('entregas.revisar');
        Route::put('/entregas/{id}/habilitar', [EntregaController::class, 'habilitar'])
            ->whereNumber('id')
            ->name('entregas.habilitar');
        Route::put('/entregas/{id}', [EntregaController::class, 'update'])
            ->whereNumber('id')
            ->name('entregas.update');
        Route::delete('/entregas/{id}', [EntregaController::class, 'destroy'])
            ->whereNumber('id')
            ->name('entregas.destroy');
    });
