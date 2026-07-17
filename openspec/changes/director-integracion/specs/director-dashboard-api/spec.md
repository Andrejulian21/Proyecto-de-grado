# Delta for Director Dashboard API

## ADDED Requirements

### Requirement: Director Dashboard Endpoints

El sistema DEBE exponer endpoints bajo `/api/director/*` que devuelvan datos agregados para el dashboard del director autenticado. Todos los endpoints aplican scope automático por `director_id = auth()->id()` y filtran solo proyectos en semestres activos.

#### Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/director/proyectos` | Proyectos supervisados en semestres activos |
| GET | `/api/director/kpis` | KPIs agregados del director |
| GET | `/api/director/entregas` | Últimas entregas pendientes de revisión |

#### Scenario: Dashboard carga datos reales en < 500ms

- GIVEN un director autenticado con 8 proyectos supervisados en semestres activos
- WHEN el frontend llama `GET /api/director/proyectos`, `GET /api/director/kpis`, `GET /api/director/entregas` concurrentemente
- THEN cada endpoint responde con status 200 y datos filtrados exclusivamente al director
- AND el tiempo total de respuesta combinado es < 500ms

#### Scenario: Director sin proyectos supervisados

- GIVEN un director sin proyectos en semestres activos
- WHEN llama a los endpoints del dashboard
- THEN `proyectos` devuelve `[]`, `kpis` devuelve contadores en cero, `entregas` devuelve `[]`
- AND todos responden 200 (no 404)

#### Scenario: Solo proyectos del director autenticado

- GIVEN existen proyectos del director A y proyectos del director B en semestres activos
- WHEN el director A consulta sus endpoints
- THEN solo recibe proyectos donde `director_id === auth()->id()`

### Requirement: DirectorKpis Response Shape

```json
{
  "proyectos_supervisando": 8,
  "entregas_pendientes": 14,
  "alertas": 2,
  "aprobadas_mes": 12
}
```

#### Scenario: KPIs calculados correctamente

- GIVEN el director tiene 5 proyectos activos, 3 entregas pendientes, 1 alerta, 2 entregas aprobadas en el mes actual
- WHEN llama `GET /api/director/kpis`
- THEN la respuesta contiene exactamente esos valores

### Requirement: DirectorProyectos Response Shape

Cada proyecto incluye: `id, code, title, status, current_phase, estudiantes (name), semestre (name, is_active)`. Ordenados por `created_at DESC`.

#### Scenario: Proyectos con estudiantes embebidos

- GIVEN un proyecto PG-2026-014 con 2 estudiantes
- WHEN se consulta `GET /api/director/proyectos`
- THEN el campo `estudiantes` contiene un array con `[{name: "Carlos Méndez"}, {name: "Ana Martínez"}]`

### Requirement: DirectorEntregas Response Shape

Últimas 10 entregas pendientes de los proyectos del director. Cada entrega incluye: `id, title, phase, status, due_date, proyecto: {id, code, title}, estudiante (nombre del primer estudiante)`.

#### Scenario: Entregas pendientes del director

- GIVEN el director tiene entregas con status `pendiente`, `enviada`, y `aprobada`
- WHEN llama `GET /api/director/entregas`
- THEN solo recibe las de status `pendiente` o `enviada`
- AND están ordenadas por `due_date` más próxima primero

## Implementation Notes

- **Controller**: `App\Http\Controllers\Api\DirectorController` (nuevo)
- **Routes**: grupo `auth:sanctum` + `role:Director` dentro de `routes/api.php`
- **DirectorController@proyectos**: `Proyecto::where('director_id', auth()->id())->enSemestresActivos()->with(['estudiantes', 'semestre'])`
- **DirectorController@kpis**: agrega datos del director — proyectos supervisando (count), entregas pendientes (count de entregas con status pendiente/enviada de sus proyectos), alertas (sum `alert_count` de sus proyectos activos), aprobadas_mes (count de entregas con status aprobada de sus proyectos este mes)
- **DirectorController@entregas**: `Entrega::whereIn('status', ['pendiente','enviada'])->whereHas('proyecto', fn => where('director_id', auth()->id()))->with(['proyecto','proyecto.estudiantes'])->latest('due_date')->limit(10)`
- **Testing**: Pest feature test — autenticar como director, crear proyectos en semestre activo, verificar endpoints filtran correctamente
