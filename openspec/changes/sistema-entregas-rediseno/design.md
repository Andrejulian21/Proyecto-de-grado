# Design: Rediseño Sistema de Entregas — Una Entrega por Grupo

## Technical Approach

Consolidar el modelo de N entregas duplicadas (una por proyecto) a **1 entrega por grupo/semestre**,
vinculada a proyectos vía pivote `entrega_proyecto`. Introducir metamodelo de **archivos requeridos**
(JSON configurable) que transforma la subida del estudiante de "subir genérico" a "completar lista
de entregables". La migración fusiona datos existentes sin pérdida.

## Architecture Decisions

### Decision: Archivos requeridos como JSON vs tabla relacional

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| JSON `archivos_requeridos` en `entregas` | Simple, rápido, sin JOINs extra. Limitado a ~20 archivos por entrega (suficiente). | **Elegida** |
| Tabla `archivos_requeridos` con FK | Más flexible pero overkill: los archivos requeridos son configuración, no datos transaccionales. | Rechazada |

**Rationale**: Los archivos requeridos son configuración estática definida por el coordinador al crear
la entrega. No se consultan independientemente ni tienen relaciones complejas. JSON en columna es
suficiente y evita JOINs innecesarios en cada query.

### Decision: Pivot con ID propio para vincular versiones

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| Agregar `id` al pivote `entrega_proyecto` + FK desde `versiones_documento` | Permite vincular versiones al contexto (entrega+proyecto) sin duplicar datos. Requiere migración del pivote. | **Elegida** |
| Mantener `entrega_id` directo en versiones | No permite distinguir qué proyecto subió qué archivo en una entrega compartida. | Rechazada |

**Rationale**: Con 1 entrega compartida por N proyectos, necesitamos saber qué proyecto subió cada
archivo. El pivot con ID propio resuelve esto sin cambiar la estructura de `versiones_documento`
drásticamente — solo agregamos FK al pivot.

### Decision: Versionamiento por archivo requerido

| Opción | Tradeoff | Decisión |
|--------|----------|----------|
| `versionamiento: boolean` por archivo requerido | Flexible: algunos archivos con historial, otros reemplazables. | **Elegida** |
| Versionamiento uniforme para toda la entrega | Simple pero inflexible: "Carta de Aval" no necesita versiones. | Rechazada |

## Data Flow

```
COORDINADOR crea entrega
    │
    ├──→ 1 fila en `entregas` (proyecto_id = NULL)
    ├──→ N filas en `entrega_proyecto` (todos los proyectos activos del semestre)
    └──→ `archivos_requeridos` JSON persistido en la entrega

ESTUDIANTE sube archivo
    │
    ├──→ POST /api/entregas/{id}/archivos/{slug}
    ├──→ Busca pivote `entrega_proyecto` para su proyecto
    ├──→ Si versionamiento=true → nueva versión (incremental)
    ├──→ Si versionamiento=false → reemplaza (borra anterior del mismo slug)
    └──→ Inserta en `versiones_documento` con `entrega_proyecto_id` + `archivo_requerido_id`

DIRECTOR revisa
    │
    ├──→ GET /api/admin/entregas/{id} → archivos agrupados por slug
    ├──→ Ve versiones por archivo requerido
    └──→ Deja observaciones por versión (director_notes)

PROYECTO NUEVO en semestre activo
    │
    └──→ Observer `Proyecto::created` → auto-attach a entregas existentes del semestre
```

## File Changes

### Migraciones

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/2026_07_24_000001_add_archivos_requeridos_to_entregas.php` | Create | Columna JSON `archivos_requeridos` en `entregas` |
| `database/migrations/2026_07_24_000002_add_entrega_proyecto_id_to_versiones_documento.php` | Create | FK `entrega_proyecto_id` + columna `archivo_requerido_id` + `descontinuado` en `versiones_documento` |
| `database/migrations/2026_07_24_000003_migrate_duplicate_entregas.php` | Create | Fusión de entregas duplicadas + migración de versiones |

### Modelos

| File | Action | Description |
|------|--------|-------------|
| `app/Models/Entrega.php` | Modify | Agregar `archivos_requeridos` a fillable/casts. Helper `getArchivoRequerido(string $slug)`. |
| `app/Models/VersionDocumento.php` | Modify | Agregar `entrega_proyecto_id`, `archivo_requerido_id`, `descontinuado` a fillable. Relación `entregaProyecto()`. |
| `app/Models/EntregaProyecto.php` | Create | Modelo pivote con `entrega_id`, `proyecto_id`. Relación `versiones()`. |

### Controladores

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Controllers/Admin/EntregaController.php` | Modify | `store()` crea 1 entrega + pivotes. `show()` devuelve archivos agrupados. `update()` valida archivos_requeridos. |
| `app/Http/Controllers/Api/EntregaEstudianteController.php` | Create | `subirArchivoPorSlug()` + `estadoCompletitud()`. |

### FormRequests

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Requests/StoreEntregaRequest.php` | Create | Validación de `archivos_requeridos` (array, min:1, ids únicos). |
| `app/Http/Requests/UpdateEntregaRequest.php` | Create | Validación + regla: no cambiar versionamiento si hay versiones. |

### Observers

| File | Action | Description |
|------|--------|-------------|
| `app/Observers/ProyectoObserver.php` | Create | Auto-attach a entregas del semestre en `created()`. |

### Frontend

| File | Action | Description |
|------|--------|-------------|
| `resources/js/components/coordinador/ArchivosRequeridosBuilder.tsx` | Create | Builder de archivos requeridos (agregar/editar/eliminar/reordenar). |
| `resources/js/pages/coordinador/CoordinadorEntregas.tsx` | Modify | Integrar builder en formularios de crear/editar. |
| `resources/js/pages/estudiante/DetalleEntregaEstudiante.tsx` | Modify | Renderizar archivos requeridos con subida individual. |
| `resources/js/pages/director/RevisionEntregaDirector.tsx` | Modify | Revisión agrupada por archivo requerido. |
| `resources/js/types/entregas.ts` | Modify | Interfaces `ArchivoRequeridoConfig`, `ArchivoRequeridoEstado`. |
| `resources/js/hooks/useEntregas.ts` | Modify | Nuevos hooks para subida por slug y estado de completitud. |

## Interfaces / Contracts

### Migration 1: `add_archivos_requeridos_to_entregas`

```php
public function up(): void
{
    Schema::table('entregas', function (Blueprint $table) {
        $table->json('archivos_requeridos')
            ->nullable()
            ->after('hora_maxima')
            ->comment('Array of {id, nombre, versionamiento} objects');
    });
}

public function down(): void
{
    Schema::table('entregas', function (Blueprint $table) {
        $table->dropColumn('archivos_requeridos');
    });
}
```

### Migration 2: `add_entrega_proyecto_id_to_versiones_documento`

```php
public function up(): void
{
    // 1. Add auto-increment ID to entrega_proyecto pivot
    Schema::table('entrega_proyecto', function (Blueprint $table) {
        $table->dropUnique(['entrega_id', 'proyecto_id']);
    });
    Schema::table('entrega_proyecto', function (Blueprint $table) {
        $table->bigIncrements('id')->first();
        $table->unique(['entrega_id', 'proyecto_id']);
    });

    // 2. Add FK + columns to versiones_documento
    Schema::table('versiones_documento', function (Blueprint $table) {
        $table->unsignedBigInteger('entrega_proyecto_id')
            ->nullable()
            ->after('entrega_id');
        $table->string('archivo_requerido_id', 100)
            ->nullable()
            ->after('entrega_proyecto_id');
        $table->boolean('descontinuado')
            ->default(false)
            ->after('archivo_requerido_id');

        $table->foreign('entrega_proyecto_id')
            ->references('id')
            ->on('entrega_proyecto')
            ->onDelete('cascade');

        $table->index(['entrega_proyecto_id', 'archivo_requerido_id']);
    });
}

public function down(): void
{
    Schema::table('versiones_documento', function (Blueprint $table) {
        $table->dropForeign(['entrega_proyecto_id']);
        $table->dropIndex(['entrega_proyecto_id', 'archivo_requerido_id']);
        $table->dropColumn(['entrega_proyecto_id', 'archivo_requerido_id', 'descontinuado']);
    });
    Schema::table('entrega_proyecto', function (Blueprint $table) {
        $table->dropPrimary('id');
    });
}
```

### Migration 3: `migrate_duplicate_entregas` (data migration)

```php
public function up(): void
{
    DB::transaction(function () {
        // Group entregas by (semester_id, title) — keep the first, merge the rest
        $groups = DB::table('entregas')
            ->select('semester_id', 'title')
            ->selectRaw('MIN(id) as keeper_id')
            ->selectRaw('GROUP_CONCAT(id) as all_ids')
            ->groupBy('semester_id', 'title')
            ->get();

        foreach ($groups as $group) {
            $allIds = explode(',', $group->all_ids);
            $keeperId = (int) $group->keeper_id;
            $duplicates = array_diff($allIds, [$keeperId]);

            if (empty($duplicates)) continue;

            // Migrate direct proyecto_id links to pivot
            foreach ($allIds as $eid) {
                $proyectoId = DB::table('entregas')->where('id', $eid)->value('proyecto_id');
                if ($proyectoId) {
                    DB::table('entrega_proyecto')->updateOrInsert(
                        ['entrega_id' => $keeperId, 'proyecto_id' => $proyectoId],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            // Migrate versiones_documento to keeper
            foreach ($duplicates as $dupId) {
                DB::table('versiones_documento')
                    ->where('entrega_id', $dupId)
                    ->update(['entrega_id' => $keeperId]);
            }

            // Delete duplicate entregas
            DB::table('entregas')->whereIn('id', $duplicates)->delete();
        }

        // Seed default archivos_requeridos for existing entregas
        DB::table('entregas')
            ->whereNull('archivos_requeridos')
            ->update([
                'archivos_requeridos' => json_encode([
                    ['id' => 'documento', 'nombre' => 'Documento', 'versionamiento' => true],
                ]),
            ]);
    });
}

public function down(): void
{
    // Data migration rollback not supported — restore from backup
}
```

### StoreEntregaRequest

```php
public function rules(): array
{
    return [
        'grupo_id' => 'required|exists:semestres,id',
        'fase' => 'required|string|max:50',
        'titulo' => 'required|string|max:255',
        'descripcion' => 'required|string|max:500',
        'fecha_limite' => 'required|date',
        'fecha_inicio' => 'nullable|date|before_or_equal:fecha_limite',
        'hora_inicio' => 'nullable|string|max:10',
        'criterios' => 'nullable|string',
        'hora_maxima' => 'nullable|string|max:10',
        'archivos_requeridos' => [
            'required', 'array', 'min:1',
            function (string $attr, mixed $value, Closure $fail) {
                $ids = collect($value)->pluck('id');
                if ($ids->duplicates()->isNotEmpty()) {
                    $fail('Los IDs de archivos requeridos deben ser únicos.');
                }
            },
        ],
        'archivos_requeridos.*.id' => 'required|string|max:100|regex:/^[a-z0-9_]+$/',
        'archivos_requeridos.*.nombre' => 'required|string|max:255',
        'archivos_requeridos.*.versionamiento' => 'required|boolean',
    ];
}
```

### UpdateEntregaRequest

```php
public function rules(): array
{
    $entregaId = $this->route('entrega'); // resolved from route

    return [
        // ... same as store minus grupo_id ...
        'archivos_requeridos' => [
            'sometimes', 'array', 'min:1',
            function (string $attr, mixed $value, Closure $fail) use ($entregaId) {
                // Check: cannot change versionamiento if versions exist
                $entrega = Entrega::findOrFail($entregaId);
                $existing = collect($entrega->archivos_requeridos ?? []);
                $incoming = collect($value);

                foreach ($incoming as $item) {
                    $old = $existing->firstWhere('id', $item['id']);
                    if ($old && isset($item['versionamiento']) && $old['versionamiento'] !== $item['versionamiento']) {
                        $hasVersions = VersionDocumento::where('entrega_id', $entregaId)
                            ->where('archivo_requerido_id', $item['id'])
                            ->exists();
                        if ($hasVersions) {
                            $fail("No se puede cambiar el versionamiento de '{$item['id']}' porque ya tiene versiones subidas.");
                        }
                    }
                }
            },
        ],
    ];
}
```

### EntregaEstudianteController — `subirArchivoPorSlug()`

```
POST /api/entregas/{id}/archivos/{slug}

1. Validate user is student of linked project
2. Validate entrega has archivo_requerido matching $slug
3. Find entrega_proyecto pivot for (entrega, user's project)
4. If versionamiento=false → delete previous versions for this slug+pivot
5. If versionamiento=true → check MAX_VERSIONS per slug (4)
6. Store file: entregas/{entrega_id}/{slug}/v{n}_{original}
7. Create VersionDocumento with entrega_proyecto_id + archivo_requerido_id
8. Return 201
```

### EntregaEstudianteController — `estadoCompletitud()`

```
GET /api/entregas/{id}/estado

1. Find user's proyecto via pivot
2. For each archivo_requerido in entrega:
   - Check if VersionDocumento exists for (entrega_proyecto_id, archivo_requerido_id)
   - If versionamiento=false: any version = complete
   - If versionamiento=true: at least 1 version = complete
3. Return {completos, pendientes, archivos: [{id, nombre, completo, versiones_count}]}
```

### TypeScript Interfaces

```typescript
// types/entregas.ts

export interface ArchivoRequeridoConfig {
  id: string;
  nombre: string;
  versionamiento: boolean;
}

export interface ArchivoRequeridoEstado {
  id: string;
  nombre: string;
  versionamiento: boolean;
  completo: boolean;
  versiones_count: number;
  ultima_version?: {
    id: number;
    file_path: string;
    original_name: string;
    uploaded_at: string;
    director_notes?: string;
  };
}

export interface EntregaEstadoResponse {
  completos: number;
  pendientes: number;
  archivos: ArchivoRequeridoEstado[];
}
```

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `StoreEntregaRequest` validation rules (ids únicos, min:1, formato slug) | Pest unit tests con Validator mock |
| Unit | `UpdateEntregaRequest` versionamiento lock rule | Pest con VersionDocumento factory |
| Feature | `store()` crea 1 entrega + N pivotes | Pest con actingAs Coordinador |
| Feature | `subirArchivoPorSlug()` con versionamiento true/false | Pest multipart upload |
| Feature | `estadoCompletitud()` returns correct status | Pest con versiones pre-creadas |
| Feature | `ProyectoObserver` auto-attach en created | Pest con factory |
| Integration | Data migration: fusiona duplicados, preserva versiones | Pest con DB seed manual |
| E2E | Flujo coordinador→estudiante→director con archivos requeridos | Playwright |

## Migration / Rollout

1. **Pre-migración**: Backup de tablas `entregas`, `entrega_proyecto`, `versiones_documento`.
2. **Migración 1**: Agregar columna `archivos_requeridos` (non-breaking, nullable).
3. **Migración 2**: Agregar columnas a `versiones_documento` + ID al pivote (non-breaking, nullable).
4. **Migración 3**: Fusión de datos + seed default. Transaccional.
5. **Deploy backend**: Nuevos endpoints coexisten con legacy (`subirVersion` marcado `@deprecated`).
6. **Deploy frontend**: Coordinador primero, luego estudiante, luego director.
7. **Post-migración**: Verificar `SELECT COUNT(*) FROM entregas` vs `SELECT COUNT(DISTINCT semester_id, title) FROM entregas` — deben coincidir.

## Open Questions

- [ ] ¿Mantener endpoint legacy `POST /api/entregas/{id}/versiones` con `@deprecated` por 1 sprint o eliminar directamente?
- [ ] ¿El `MAX_VERSIONS = 4` actual aplica por archivo requerido o por entrega completa en el nuevo modelo?
