# Design: Entregas y Evaluación de Evaluadores

> Change: `entregas-evaluacion` | Modo: hybrid | 14 RFs | 3 capacidades | 5 PRs encadenados

## Técnicos Resueltos (TBDs de la spec)

| # | TBD | Resolución |
|---|-----|-----------|
| 1 | `EntregaPesoService` — ¿resolución de `semester_id` vía pivot? | `semester_id` ya es columna directa en `entregas` (no solo pivot). El servicio valida suma sobre `Entrega::where('semester_id', $id)->whereIn('phase', $par)->whereNotNull('grade_percentage')`. Sin resolución de pivots necesaria. |
| 2 | Slug archivo principal — ¿alinear `documento-proyecto` con datos existentes (`documento`)? | Validación backend exige `slug === 'documento-proyecto'` en store/update. Datos existentes con `'documento'` quedan intactos (no se backfill). Frontend cambia default a `'documento-proyecto'`. |
| 3 | Extracción de Actions del `EntregaController` (812 líneas) | Extraer 5 Actions: `StoreEntregaAction`, `UpdateEntregaAction`, `ReviewEntregaAction`, `SolicitarEntregaAction`, `HabilitarEntregaAction`. Controlador queda como thin layer (~380 líneas). |
| 4 | Payload `GET /api/evaluador/asignaciones/{id}/detalle` — eager-loads anti N+1 | `EvaluadorProyecto` no tiene `entrega_id`. Se resuelve vía `Entrega::where('semester_id', $proyecto->semester_id)->where('phase', $faseMapeada)->with(['versiones' => fn($q) => $q->latest()])->first()`. Mapeo de fase: `'Anteproyecto' → 'anteproyecto'`, `'Final' → 'presentacion_final'`. |
| 5 | Idempotencia `POST /evaluar` — UNIQUE + 409 | `evaluaciones_evaluador` tiene `UNIQUE(evaluador_proyecto_id)`. Controlador chequea existencia previa y devuelve 409 antes de intentar insert. |
| 6 | Punto de inserción de `director_grade` — ¿cuándo aparece? | Aparece en la vista de revisión cuando `entrega.status !== 'aprobada' && entrega.status !== 'rechazada' && entrega.due_date >= today()`. No hay estado literal "activa" en el enum; se deriva de la negación de estados terminales. |
| 7 | Plan de migraciones sin backfill | 3 migraciones aditivas, todas nullable/default: (1) `grade_percentage` + `director_grade` en `entregas`, (2) `evaluado` en `evaluador_proyecto`, (3) tabla `evaluaciones_evaluador`. Sin backfill obligatorio. |

---

## Enfoque Técnico

Tres capacidades entregadas en 5 PRs encadenados (< 400 líneas cada uno). Cada PR es independiente en tests y puede revertirse sin afectar los otros.

- **PR1 — Schema**: Migraciones + modelos actualizados. Foundation para los demás.
- **PR2 — Entregas refactor**: Extracción de Actions + validaciones de `archivos_requeridos` + `grade_percentage` + `EntregaPesoService`.
- **PR3 — Evaluador backend**: 3 endpoints + `EvaluacionEvaluador` model + controller.
- **PR4 — Nota director**: `director_grade` en `revisar` + validaciones de edición condicional.
- **PR5 — Frontend**: `ArchivosRequeridosBuilder` (toggle IA) + `CoordinadorEntregas` (indicador %) + `MisAsignaciones` + `EvaluadorCalificar` conectado a API.

---

## Decisiones de Arquitectura

| # | Decisión | Opciones | Riesgo | Elección |
|---|----------|----------|--------|----------|
| D1 | Ubicación de `director_grade` | a) `entregas` b) `versiones_documento` | Nota por entrega (más estable) vs por versión (más granular) | `entregas` — la nota es del director sobre la entrega, no de una versión específica. |
| D2 | JSON `archivos_requeridos` vs tabla relacional | a) Extender JSON (status quo) b) Nueva tabla `archivos_requeridos` | JSON es menos queryable pero el proyecto ya usa JSON para ~2-6 archivos | Extender JSON — consistencia con diseño actual, sin migración de datos. |
| D3 | Evaluación del evaluador — tabla nueva o reutilizar `evaluaciones` | a) `evaluaciones_evaluador` b) Agregar campos a `evaluaciones` | `evaluaciones` actual es por-criterio (sum ≤ 100), semántica incompatible | Nueva tabla `evaluaciones_evaluador` — separación clara asignación vs evaluación. |
| D4 | Fase del evaluador — mapeo a fase de entrega | `EvaluadorProyecto.fase` usa `'Anteproyecto'` / `'Final'`. Entrega usa `'anteproyecto'` / `'presentacion_final'` | Mapeo en runtime o migración de datos | Mapeo en runtime en el controller — evita migración de datos existentes. |
| D5 | Controller thin vs fat después del refactor | a) Controller delega 100% a Actions b) Controller mantiene lógica de autorización | Autorización por rol es transversal y repetida | Controller mantiene autorización + llamada a Action — Actions son puras de dominio. |
| D6 | Regla de pesos — ¿servicio o FormRequest? | a) `EntregaPesoService` b) Validador custom en FormRequest | Reutilización (Update también necesita) | `EntregaPesoService` — compartido entre Store y Update, testeable unitariamente. |

---

## Flujo de Datos

### PR2 — Crear entrega con peso (coordinador)
```
CoordinadorEntregas.tsx
    ├── POST /api/admin/entregas
    │   └── StoreEntregaRequest
    │       ├── valida slug 'documento-proyecto' exists
    │       ├── valida analizable_ia solo en slug principal
    │       ├── valida grade_percentage 0-100
    │       └── delega a EntregaPesoService::validarSumaPar($semesterId, $fase, $nuevoPeso)
    │           └── consulta entregas del semestre para el par de fases
    │           └── bloquea si suma > 100 o si par completo ≠ 100
    └── StoreEntregaAction
        └── Entrega::create([...])
```

### PR3 — Evaluador envía nota
```
MisAsignaciones.tsx
    ├── GET /api/evaluador/mis-asignaciones
    │   └── EvaluadorAsignacionesController::index
    │       └── EvaluadorProyecto::where('evaluador_id', $user->id)
    │       └── with('proyecto.estudiantes', 'proyecto.director')
    └── Toggle "Ver ya evaluados" → filtra por evaluado=true

EvaluadorCalificar.tsx
    ├── GET /api/evaluador/asignaciones/{id}/detalle
    │   └── resuelve entrega activa del semestre+fase
    │   └── eager-load: versiones (latest), proyecto, director
    ├── POST /api/evaluador/asignaciones/{id}/evaluar
    │   └── 409 si ya existe evaluación (UNIQUE constraint)
    │   └── EvaluacionEvaluador::create([...])
    │   └── EvaluadorProyecto::update(['evaluado' => true])
```

### PR4 — Director asigna nota al aprobar
```
Director revisa entrega
    ├── PUT /api/admin/entregas/{id}/revisar
    │   └── ReviewEntregaAction
    │       ├── Valida entrega editable (no terminal, due_date ≥ now)
    │       ├── Persiste director_notes en VersionDocumento
    │       ├── Persiste director_grade en Entrega (si aprobada)
    │       └── Auto-advance phase si aprobada
```

---

## Cambios de Archivos

### PR1 — Schema Foundation
| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `database/migrations/2026_08_04_000001_add_grade_and_director_grade_to_entregas.php` | Crear | `grade_percentage` (decimal 5,2 nullable) + `director_grade` (decimal 4,2 nullable) |
| `database/migrations/2026_08_04_000002_add_evaluado_to_evaluador_proyecto.php` | Crear | `evaluado` boolean default false |
| `database/migrations/2026_08_04_000003_create_evaluaciones_evaluador_table.php` | Crear | Tabla con FK UNIQUE a `evaluador_proyecto` |
| `app/Models/Entrega.php` | Modificar | Agregar `grade_percentage`, `director_grade` a fillable y casts |
| `app/Models/EvaluadorProyecto.php` | Modificar | Agregar `evaluado` a fillable y casts |
| `app/Models/EvaluacionEvaluador.php` | Crear | Modelo nuevo con relación a `EvaluadorProyecto` |

### PR2 — Entregas Backend
| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `app/Actions/Entrega/StoreEntregaAction.php` | Crear | Lógica pura de creación de entrega |
| `app/Actions/Entrega/UpdateEntregaAction.php` | Crear | Lógica pura de actualización |
| `app/Actions/Entrega/ReviewEntregaAction.php` | Crear | Lógica de aprobación/rechazo + notas |
| `app/Actions/Entrega/SolicitarEntregaAction.php` | Crear | Lógica de solicitud de habilitación |
| `app/Actions/Entrega/HabilitarEntregaAction.php` | Crear | Lógica de habilitación por director |
| `app/Services/EntregaPesoService.php` | Crear | `validarSumaPar($semesterId, $fase, $nuevoPeso): void` |
| `app/Http/Requests/StoreEntregaRequest.php` | Modificar | + `grade_percentage`, validación slug, `analizable_ia` |
| `app/Http/Requests/UpdateEntregaRequest.php` | Modificar | Mismas validaciones para update |
| `app/Http/Controllers/Admin/EntregaController.php` | Modificar | Delega a Actions; queda < 400 líneas |

### PR3 — Evaluador Backend
| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `app/Http/Controllers/Api/EvaluadorAsignacionesController.php` | Crear | 3 endpoints: mis-asignaciones, detalle, evaluar |
| `routes/api.php` | Modificar | Grupo `/api/evaluador/*` bajo middleware auth |

### PR4 — Nota Director
| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `app/Http/Controllers/Admin/EntregaController.php` | Modificar | `revisar` ahora acepta `director_grade` + valida editable |
| `app/Actions/Entrega/ReviewEntregaAction.php` | Modificar | Persiste `director_grade` en Entrega |

### PR5 — Frontend
| Archivo | Acción | Descripción |
|---------|--------|-------------|
| `resources/js/components/entregas/ArchivosRequeridosBuilder.tsx` | Modificar | Toggle "Analizable con IA" (solo archivo principal) |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Modificar | Campo % nota + indicador visual de suma del par |
| `resources/js/pages/evaluador/MisAsignaciones.tsx` | Crear | Cards + toggle "Ver ya evaluados" |
| `resources/js/pages/evaluador/EvaluadorCalificar.tsx` | Modificar | Conecta a API real, muestra archivos/notas director |
| `resources/js/hooks/useEvaluadorAsignaciones.ts` | Crear | Hook para mis-asignaciones y detalle |
| `resources/js/types/entregas.ts` | Modificar | + `analizable_ia`, `grade_percentage`, `director_grade` |

---

## Interfaces / Contracts

### API Contracts

#### POST /api/admin/entregas (Store)
```json
{
  "grupo_id": 1,
  "fase": "anteproyecto",
  "titulo": "Entrega 1",
  "descripcion": "...",
  "fecha_limite": "2026-08-15",
  "grade_percentage": 60,
  "archivos_requeridos": [
    { "id": "documento-proyecto", "nombre": "Documento del proyecto", "versionamiento": true, "analizable_ia": true },
    { "id": "anexo", "nombre": "Anexo", "versionamiento": false }
  ]
}
```

#### GET /api/evaluador/mis-asignaciones
```json
{
  "data": [
    {
      "id": 1,
      "proyecto": { "id": 5, "codigo": "PG-2026-001", "titulo": "...", "estudiantes": [...], "director": {...} },
      "fase": "anteproyecto",
      "evaluado": false,
      "created_at": "2026-08-01"
    }
  ]
}
```

#### GET /api/evaluador/asignaciones/{id}/detalle
```json
{
  "proyecto": { ... },
  "fase": "anteproyecto",
  "entrega": {
    "id": 10,
    "archivos_requeridos": [...],
    "due_date": "2026-08-15",
    "director_grade": 4.5,
    "versiones_documento": [{ "version_number": 1, "file_path": "...", "director_notes": "..." }]
  },
  "evaluacion": null | { "nota": 4.5, "observaciones": "...", "evaluated_at": "2026-08-03" }
}
```

#### POST /api/evaluador/asignaciones/{id}/evaluar
```json
{ "nota": 4.5, "observaciones": "Documento bien estructurado" }
```
Response: 201 si éxito, 409 si ya evaluado, 422 si nota fuera de rango.

### Service Contract

```php
final class EntregaPesoService
{
    /**
     * @throws ValidationException si la suma del par excede 100 o si el par completo no suma 100
     */
    public function validarSumaPar(int $semesterId, string $fase, ?float $nuevoPeso): void;
    
    public function obtenerSumaPar(int $semesterId, array $fasesPar): float;
    
    public function fasesDelPar(string $fase): array; // ['anteproyecto', 'presentacion_anteproyecto']
}
```

---

## Estrategia de Testing

| Capa | Qué testear | Cómo |
|------|-------------|------|
| Unit | `EntregaPesoService` | Pest: casos de suma < 100, = 100, > 100, NULL no participa, par incompleto |
| Unit | `StoreEntregaRequest` validaciones | Pest: slug obligatorio, analizable_ia rechazado en secundario, grade_percentage 0-100 |
| Feature | CRUD entregas con nuevos campos | Pest: store con peso válido, store bloqueado por suma > 100, update con cambio a NULL |
| Feature | Endpoints evaluador | Pest: mis-asignaciones aisladas por evaluador, detalle con contexto completo, evaluar 201/409/422 |
| Feature | Nota director | Pest: revisar con director_grade persiste, editar rechazado si entrega cerrada |
| E2E | Flujo evaluador completo | Playwright: login evaluador → ver cards → click evaluar → enviar nota → ver solo lectura |

**Baseline:** 495 tests. Target post-change: 495 + ~25 nuevos = 520.

---

## División en PRs Encadenados

| PR | Alcance | Líneas estimadas | Dependencia |
|----|---------|------------------|-------------|
| PR1 | 3 migraciones + modelos (`Entrega`, `EvaluadorProyecto`, `EvaluacionEvaluador`) | ~120 líneas | Ninguna |
| PR2 | 5 Actions + `EntregaPesoService` + FormRequests + refactor controller entregas | ~350 líneas | PR1 |
| PR3 | `EvaluadorAsignacionesController` + 3 endpoints + routes | ~180 líneas | PR1 |
| PR4 | `ReviewEntregaAction` modificado + validación `director_grade` editable | ~80 líneas | PR1, PR2 |
| PR5 | Frontend: `ArchivosRequeridosBuilder` + `CoordinadorEntregas` + `MisAsignaciones` + `EvaluadorCalificar` + hooks + types | ~320 líneas | PR1-PR4 |

---

## Migración / Rollout

- **Sin backfill obligatorio**: entregas existentes quedan con `grade_percentage = NULL` y `director_grade = NULL`.
- **Rollback**: `migrate:rollback` 3 migraciones. Sin pérdida de datos (columnas nullable).
- **Feature flags**: No se requieren. Los endpoints nuevos no afectan rutas existentes.
- **Orden de deploy**: PR1 → PR2 → PR3 → PR4 → PR5 (encadenados por dependencias).

---

## Open Questions

- [ ] Ninguna bloqueante. Todos los TBDs resueltos arriba.
