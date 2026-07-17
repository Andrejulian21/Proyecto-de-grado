# Design: Director Integration — Conexión Frontend ↔ Backend

## Resumen Técnico

Conectar el rol Director a APIs reales en 4 PRs encadenados. El frontend actualmente usa mock data (`MOCK_KPIS`, `MOCK_BINNACLES`, etc.). Se crean 3 endpoints nuevos (`DirectorController`), se extienden 2 existentes (`BitacoraController@firmar`, `EvaluacionController@store`), y se reemplazan todos los mocks con hooks que usan `apiFetch`.

**Corrección global**: La nota es **0.0 a 5.0** (1 decimal). Afecta `EvaluacionController@store` (`grade` max:5), `EvaluacionController@consolidado` (fórmula ajustada a 0-5) y `EntregaController@revisar` (`consolidated_grade` max:5).

---

## PR 1 — Navbar + Dashboard

### Arquitectura

```
Sidebar.tsx (fix nav) ──→ DirectorDashboard.tsx (3 hooks)
                                  │
                    ┌─────────────┼─────────────┐
                    ▼             ▼              ▼
            useDirectorProyectos  useDirectorKpis  useDirectorEntregas
                    │             │              │
                    ▼             ▼              ▼
            GET /api/director/   GET /api/director/  GET /api/director/
            proyectos            kpis                entregas
                    │             │              │
                    └─────────────┼──────────────┘
                                  ▼
                      DirectorController.php (NEW)
```

### Backend — `DirectorController.php` (NEW)

**File**: `app/Http/Controllers/Api/DirectorController.php`

```php
class DirectorController extends Controller
{
    // GET /api/director/proyectos
    public function proyectos(Request $request): JsonResponse
    {
        $user = $request->user();
        $proyectos = Proyecto::where('director_id', $user->id)
            ->enSemestresActivos()
            ->with(['estudiantes:id,name', 'semestre:id,name,is_active'])
            ->get();
        return response()->json(['data' => $proyectos]);
    }

    // GET /api/director/kpis
    public function kpis(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $proyectos = Proyecto::where('director_id', $userId)
            ->enSemestresActivos()->pluck('id');

        return response()->json([
            'proyectos_supervisando' => $proyectos->count(),
            'entregas_pendientes'    => Entrega::whereIn('proyecto_id', $proyectos)
                                          ->where('status', 'enviada')->count(),
            'alertas'                => Proyecto::whereIn('id', $proyectos)
                                          ->where('status', 'en_riesgo')->count(),
            'aprobadas_mes'          => Entrega::whereIn('proyecto_id', $proyectos)
                                          ->where('status', 'aprobada')
                                          ->whereMonth('updated_at', now()->month)->count(),
        ]);
    }

    // GET /api/director/entregas
    public function entregas(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $entregas = Entrega::whereHas('proyecto', fn($q) => $q->where('director_id', $userId))
            ->orWhereHas('proyectos', fn($q) => $q->where('director_id', $userId))
            ->with(['proyecto:id,code,title', 'proyectos:id,code,title'])
            ->orderByDesc('created_at')->limit(20)->get();
        // Map con estudiante nombre desde proyecto.estudiantes
        return response()->json(['data' => $entregas]);
    }
}
```

**Rutas nuevas** en `routes/api.php` (dentro del grupo `auth:sanctum`):
```php
Route::prefix('director')->name('director.')->group(function () {
    Route::get('/proyectos', [DirectorController::class, 'proyectos']);
    Route::get('/kpis', [DirectorController::class, 'kpis']);
    Route::get('/entregas', [DirectorController::class, 'entregas']);
});
```

### Frontend

**Sidebar.tsx** — Cambios en `navConfig.Director`:
```tsx
Director: [
    { to: '/dashboard/director', label: 'Panel', icon: LayoutDashboard },
    { to: '/supervision',        label: 'Supervisión', icon: FolderKanban },
    { to: '/evaluaciones',       label: 'Evaluaciones', icon: ClipboardCheck },
    { to: '/anuncios',           label: 'Anuncios', icon: Megaphone },
    { to: '/recursos',           label: 'Recursos', icon: FolderKanban },
],
```
- Eliminar: `/bitacoras`, `/bitacoras/proyectos`
- Agregar `/dashboard/director` al array de `end` en NavLink
- Supervisión: custom `isActive` → `location.pathname.startsWith('/supervision')`

**3 hooks nuevos** (patrón `useReducer` + `apiFetch`, igual que `useProyectos`):
- `useDirectorProyectos.ts` → `GET /api/director/proyectos`
- `useDirectorKpis.ts` → `GET /api/director/kpis`
- `useDirectorEntregas.ts` → `GET /api/director/entregas`

**DirectorDashboard.tsx** — Reestructuración:
1. Eliminar: `MOCK_KPIS`, `MOCK_PROGRESS`, `MOCK_DELIVERIES`, `PhaseStepper`
2. Usar los 3 hooks con `Promise.all` para carga concurrente
3. KPI cards con datos reales (4 `StatCard`)
4. Carrusel horizontal de proyectos (max 5 cards con progreso)
5. Tabla de últimas entregas con `DataTable` + datos reales
6. Estados: loading skeletons, error con retry, empty state

### Data Flow — Dashboard

```
Component mount
    │
    ├─ useEffect → Promise.all([
    │     fetchProyectos(),   → GET /api/director/proyectos
    │     fetchKpis(),        → GET /api/director/kpis
    │     fetchEntregas()     → GET /api/director/entregas
    │   ])
    │
    ├─ loading=true → render skeletons (4 StatCard skeletons + 3 card skeletons)
    │
    ├─ success → dispatch FETCH_SUCCESS × 3
    │   ├─ KPIs → 4 StatCard con valores reales
    │   ├─ Proyectos → carrusel horizontal (max 5)
    │   └─ Entregas → DataTable (columnas: estudiante, proyecto, tipo, fecha, estado)
    │
    └─ error → dispatch FETCH_ERROR → error banner + retry button
```

### Testing PR 1
- **Feature test**: `tests/Feature/DirectorDashboardTest.php`
  - Director con proyectos en semestre activo → 200 + datos correctos
  - Director sin proyectos → arrays vacíos, KPIs en 0
  - Director con proyectos en semestre inactivo → no aparecen
  - Estudiante/Evaluador → 403 en endpoints `/director/*`
- **Unit test**: Verificar que el scope `enSemestresActivos()` filtra correctamente

---

## PR 2 — Supervisión + Bitácoras + Detalle Entrega

### Arquitectura

```
SupervisionProyectoDirector.tsx
    │
    ├─ Lista: GET /api/director/proyectos → cards grid
    │   └─ Click "Ver Proyecto" → /supervision/:id
    │
    └─ Detalle: GET /api/admin/proyectos/:id (reuse)
         ├─ Entregas: GET /api/admin/entregas?proyecto_id=:id (reuse)
         ├─ Bitácoras: GET /api/director/proyectos/:id/bitacoras (NEW)
         └─ Firmar: POST /api/bitacoras/:id/firmar (EXTEND)
```

### Backend

**NEW**: `GET /api/director/proyectos/{id}/bitacoras`
- En `DirectorController` o como método en `BitacoraController`
- Valida que el user sea director del proyecto
- Retorna bitácoras con `signature_status`, `duration_hours`, `meeting_date`

**MODIFY**: `BitacoraController@firmar` — Extender flujo:
```
Actual:
  Pendiente → solo estudiante firma → FirmadaEstudiante
  FirmadaEstudiante → solo director firma → Completada

Nuevo (agregar rama alternativa):
  Pendiente → si es director del proyecto → firma directa → Completada
  (sin pasar por FirmadaEstudiante)
```

Código a agregar en `firmar()`:
```php
if ($currentStatus === EstadoFirma::Pendiente->value) {
    // Existing: student signs...
    // NEW: check if director signing directly
    if ($proyecto->director_id === $user->id) {
        $bitacora->update([
            'signature_status' => EstadoFirma::Completada->value,
            'director_signed_at' => now(),
        ]);
        return response()->json(['data' => $bitacora->fresh()]);
    }
    // ... existing student logic
}
```

**Ruta nueva**:
```php
Route::get('/director/proyectos/{id}/bitacoras', [DirectorController::class, 'bitacoras'])
    ->whereNumber('id');
```

### Frontend

**SupervisionProyectoDirector.tsx** — Dos modos:
1. **Lista** (ruta `/supervision`): cards grid con `useDirectorProyectos()`. Cada card muestra code, title, estudiantes, fase actual. Click → navega a `/supervision/:id`
2. **Detalle** (ruta `/supervision/:id`): datos reales del proyecto + entregas + bitácoras

**BitacorasDirector.tsx**:
- Eliminar `MOCK_BINNACLES`
- Nuevo hook `useBitacorasDirector()` que hace `GET /api/director/proyectos/:id/bitacoras` para cada proyecto del director (o un solo endpoint agregado que traiga todas las bitácoras de todos los proyectos del director)
- Botón "Firmar" → `POST /api/bitacoras/:id/firmar` con confirm modal
- Mantener: search, filter por status, DataTable

**DetalleEntregaDirector.tsx**:
- Eliminar mocks de entrega/proyecto
- `GET /api/admin/entregas/:id` + `GET /api/entregas/:id/versiones`
- Submit review → `PUT /api/admin/entregas/:id/revisar` con `{ status, consolidated_grade, director_notes }`
- **Nota**: `consolidated_grade` ahora es 0.0–5.0

### Testing PR 2
- **Feature**: Director firma bitácora desde `Pendiente` → `Completada` directamente
- **Feature**: Director NO puede firmar bitácora de proyecto ajeno (403)
- **Feature**: Bitácora ya `Completada` → 422
- **Feature**: Lista de bitácoras por proyecto solo para director autorizado
- **Feature**: Revisar entrega con nota 0.0–5.0 (validación)

---

## PR 3 — Evaluaciones (Calificación 0–5)

### Arquitectura

```
EvaluacionesDirector.tsx (NEW)
    │
    ├─ Lista: GET /api/director/evaluaciones (NEW)
    │   └─ Muestra proyectos donde es evaluador
    │
    ├─ Detalle: GET /api/director/proyectos/:id/entrega-fase?fase=X (NEW)
    │   └─ Entrega aprobada de la fase correcta
    │
    └─ Calificar: POST /api/evaluaciones (EXISTING, modify validation)
         └─ grade: 0.0–5.0 (1 decimal)
```

### Backend

**NEW**: `GET /api/director/evaluaciones` en `DirectorController`:
```php
public function evaluaciones(Request $request): JsonResponse
{
    $userId = $request->user()->id;
    // Proyectos donde el director es evaluador (excluye sus propios proyectos dirigidos)
    $proyectos = Proyecto::whereHas('evaluadores', fn($q) => $q->where('user_id', $userId))
        ->where('director_id', '!=', $userId)
        ->with(['estudiantes:id,name', 'evaluadores:id,name'])
        ->enSemestresActivos()
        ->get();
    return response()->json(['data' => $proyectos]);
}
```

**NEW**: `GET /api/director/proyectos/{id}/entrega-fase?fase=X`:
- Busca la entrega aprobada de la fase especificada para el proyecto
- Retorna entrega + versiones aprobadas

**MODIFY**: `EvaluacionController@store` — Cambiar validación:
```php
// ANTES:
'grade' => 'nullable|numeric|min:0|max:100',
// DESPUÉS:
'grade' => 'nullable|numeric|min:0|max:5',
```

**MODIFY**: `EntregaController@revisar` — Cambiar validación:
```php
// ANTES:
'consolidated_grade' => 'nullable|numeric|min:0|max:100',
// DESPUÉS:
'consolidated_grade' => 'nullable|numeric|min:0|max:5',
```

**MODIFY**: `EvaluacionController@consolidado` — Ajustar fórmula:
```php
// ANTES: round(($totalWeighted / $totalPercentage) * 100, 2)
// DESPUÉS: round($totalWeighted / $totalPercentage, 1)  // Resultado en escala 0-5
```

**Ruta nueva**:
```php
Route::get('/director/evaluaciones', [DirectorController::class, 'evaluaciones']);
Route::get('/director/proyectos/{id}/entrega-fase', [DirectorController::class, 'entregaFase']);
```

### Frontend

**EvaluacionesDirector.tsx** (NEW):
- Hook `useDirectorEvaluaciones()` → `GET /api/director/evaluaciones`
- Lista de cards: proyecto, fase, fecha, estudiantes, co-evaluadores
- Click → detalle con entrega aprobada de la fase
- Form de calificación: criterios con nota 0.0–5.0 (input type="number" step="0.1" min="0" max="5") + porcentaje
- Submit → `POST /api/evaluaciones`

### Testing PR 3
- **Feature**: Nota > 5.0 → 422 validation error
- **Feature**: Nota 4.5 → 201 creada correctamente
- **Feature**: Director ve solo proyectos donde es evaluador (no propios)
- **Feature**: Evaluador no asignado → 403
- **Feature**: Consolidado calcula promedio en escala 0-5

---

## PR 4 — Recursos (Download Fix + UI Enhancement)

### Estado Actual

`Recursos.tsx` ya está conectado a `GET /api/recursos`, pero:
1. ❌ `fromApi()` descarta `file_path` y `link` de la API — no están en el `Resource` interface
2. ❌ El botón "Descargar" en `RecursoDetalle.tsx` no tiene `onClick` — no hace nada
3. ❌ El tamaño del archivo siempre muestra `'—'` porque no se mapea desde la API
4. ⚠️ El diseño visual es básico, sin metadatos reales del archivo

### Backend

**No requiere cambios en `RecursoController.php`** — `GET /api/recursos` ya devuelve `file_path` y `link`. El problema es que el frontend no los usa.

**Verificar symlink**: Asegurar que `php artisan storage:link` esté ejecutado para que `/storage/recursos/*` sirva los archivos.

### Frontend — Fix Descarga + Mejoras UI

**Recursos.tsx**:
1. **Fix `Resource` interface**: Agregar `file_path: string | null` y `link: string | null`
2. **Fix `fromApi()`**: Mapear `file_path` y `link` desde la respuesta API. Calcular `size` real si hay `file_path` (o mostrar tipo de recurso: "Documento" / "Enlace externo")
3. **Botón descarga directa**: Cuando `file_path` exista, la card tiene un botón "Descargar" que navega a `/storage/{file_path}`. Cuando `link` exista, abre en nueva pestaña.
4. **Rediseño visual**: Cards con mejor jerarquía visual — icono badge más grande, título con tipografía mejorada, metadata en footer con tamaño/tipo real, bordes por categoría
5. **Grid responsive**: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3` (mantener)
6. **Search con debounce**: 300ms
7. **Accesibilidad**: `aria-label` en descargas, `role="article"`, focus visible
8. **Estados**: loading skeletons, error con retry, empty state por categoría

**RecursoDetalle.tsx**:
1. **Fix `ResourceDetail` interface**: Agregar `file_path` y `link`
2. **Fix `fromApi()`**: Mapear campos
3. **Fix botón "Descargar"**: Agregar `onClick` que descargue desde `/storage/{file_path}` o abra `link` en nueva pestaña
4. **Fix metadata**: Mostrar tamaño real del archivo si está disponible
5. **Rediseño**: Mejor layout, metadatos reales, breadcrumb mejorado

### Testing PR 4
- **Visual**: Verificar responsive en 320px, 768px, 1440px
- **A11y**: Tab navigation funciona, aria-labels presentes, focus visible
- **Functional**: Click "Descargar" → descarga el archivo real / abre enlace externo
- **Functional**: Filtros por categoría, search funcionan con datos reales

---

## Validaciones Transversales

| Regla | Dónde se aplica |
|-------|----------------|
| `director_id = auth()->id()` | `DirectorController` todos los endpoints |
| Solo semestres activos (`is_active = true`) | `scopeEnSemestresActivos()` en `proyectos()` |
| Director no revisa entrega de proyecto ajeno | `esDirectorDeEntrega()` en `EntregaController@revisar` |
| Director no firma bitácora de proyecto ajeno | Verificación `proyecto->director_id` en `BitacoraController@firmar` |
| Evaluador debe estar en `evaluador_proyecto` | `EvaluacionController@store` (existente) |
| Evaluador no evalúa su propio proyecto dirigido | Filter `director_id != userId` en `evaluaciones()` |
| Suma porcentajes ≤ 100% | `EvaluacionController@store` (existente) |
| Nota 0.0–5.0 con 1 decimal | `EvaluacionController@store` (`grade` max:5) y `EntregaController@revisar` (`consolidated_grade` max:5) |
| Archivo descargable solo si `file_path` existe | Frontend `Recursos.tsx` — botón condicional |
| Archivo servido por `/storage/{file_path}` | Backend symlink `public/storage` (verificar con `artisan storage:link`) |
| Nota 0.0–5.0 con 1 decimal | `EvaluacionController@store` + `EntregaController@revisar` |
| Bitácora `Completada` no se re-firma | Check status en `firmar()` (existente) |

---

## File Changes Summary

| File | PR | Action | Description |
|------|----|--------|-------------|
| `app/Http/Controllers/Api/DirectorController.php` | 1 | Create | 3 métodos: proyectos, kpis, entregas |
| `routes/api.php` | 1,2,3 | Modify | Agregar rutas `/director/*` |
| `resources/js/components/layout/Sidebar.tsx` | 1 | Modify | Trim Director nav de 7 a 5 items |
| `resources/js/hooks/useDirectorProyectos.ts` | 1 | Create | Hook fetch proyectos director |
| `resources/js/hooks/useDirectorKpis.ts` | 1 | Create | Hook fetch KPIs director |
| `resources/js/hooks/useDirectorEntregas.ts` | 1 | Create | Hook fetch entregas director |
| `resources/js/pages/dashboard/DirectorDashboard.tsx` | 1 | Modify | Eliminar mocks, usar hooks, 3 llamadas concurrentes |
| `resources/js/pages/director/SupervisionProyectoDirector.tsx` | 2 | Modify | Lista cards + detalle con datos reales |
| `resources/js/pages/director/BitacorasDirector.tsx` | 2 | Modify | Conectar a API real, botón firmar |
| `resources/js/pages/director/DetalleEntregaDirector.tsx` | 2 | Modify | Conectar revisión a endpoint real |
| `app/Http/Controllers/Api/BitacoraController.php` | 2 | Modify | Extender `firmar()` para firma directa director desde Pendiente |
| `app/Http/Controllers/Api/EvaluacionController.php` | 3 | Modify | Cambiar `grade` max:100 → max:5 |
| `app/Http/Controllers/Admin/EntregaController.php` | 3 | Modify | Cambiar `consolidated_grade` max:100 → max:5 |
| `resources/js/hooks/useDirectorEvaluaciones.ts` | 3 | Create | Hook fetch evaluaciones director |
| `resources/js/pages/director/EvaluacionesDirector.tsx` | 3 | Create | Nuevo: lista + detalle + calificación 0-5 |
| `resources/js/pages/shared/Recursos.tsx` | 4 | Modify | Fix `fromApi()` + botón descarga + rediseño UI |
| `resources/js/pages/shared/RecursoDetalle.tsx` | 4 | Modify | Fix botón "Descargar" onClick + metadata real + rediseño |

---

## Skills por PR

| PR | Skills | Justificación |
|----|--------|---------------|
| PR 1 | `laravel-patterns`, `api-design`, `react-patterns`, `react-state-management`, `tailwind-patterns` | Controller con Eloquent scopes, endpoints REST, hooks con useReducer para 3 llamadas concurrentes, carrusel horizontal con Tailwind |
| PR 2 | `laravel-patterns`, `api-design`, `laravel-tdd`, `react-patterns`, `tailwind-patterns` | Extender BitacoraController@firmar con lógica de firma directa, tests con Pest para el flujo de firma, cards grid de supervisión |
| PR 3 | `laravel-patterns`, `api-design`, `laravel-tdd`, `database-design` | Validación nota 0-5, test de límites, revisar modelo evaluador_proyecto para alinear con Coordinador |
| PR 4 | `tailwind-patterns`, `react-patterns`, `file-uploads`, `accessibility`, `testing-patterns` | Rediseño UI de cards, fix descarga con Storage facade, a11y en botones de descarga, tests de descarga funcional |

---

## Open Questions

- [ ] ¿El hook `useBitacorasDirector` necesita un endpoint que traiga bitácoras de TODOS los proyectos del director de una vez, o se hace un call por proyecto? → **Decisión: crear endpoint agregador** `GET /api/director/bitacoras` que retorne bitácoras de todos los proyectos del director.
- [ ] ¿La vista de EvaluacionesDirector necesita mostrar los criterios predefinidos o el evaluador los crea libremente? → **Decisión: el evaluador define criterio + porcentaje + nota libremente** (igual que el flujo existente de EvaluacionController@store).

---

## Migration / Rollout

No migration required. Todos los cambios son aditivos (nuevos endpoints, nuevos componentes) o modificaciones de validación (max:100 → max:5). La nota 0-5 es un cambio de validación que NO requiere migración de datos — las evaluaciones existentes con notas en 0-100 quedan como están (datos históricos).
